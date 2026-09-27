<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\NotificationInterface;
use App\Mail\InviteMail;
use App\Mail\PasswordResetMail;
use App\Mail\NotificationMail;
use App\Messaging\PermanentFailureException;
use App\Messaging\RetryPolicy;
use App\Services\RabbitMQConsumer;
use App\Services\RabbitMQPublisher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeEmailQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customs:consume-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for consuming the email queue and sending the invite and notification emails';

    private const QUEUE = 'emails.queue';

    private const INVITE_KEY = 'invites.email';

    private const PASSWORD_RESET_KEY = 'password-resets.email';

    private const NOTIFICATION_KEY = 'notifications.email';

    public function __construct(
        private RabbitMQConsumer $consumer,
        private RabbitMQPublisher $publisher,
        private RetryPolicy $retryPolicy,

        private InviteInterface $inviteRepository,
        private NotificationInterface $notificationRepository
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Waiting for messages on ' . self::QUEUE . '...');

        $this->consumer->consume(
            self::QUEUE,
            [self::INVITE_KEY, self::PASSWORD_RESET_KEY, self::NOTIFICATION_KEY],
            fn(AMQPMessage $message) => $this->dispatch($message),
            config('messaging.retry.tiers_ms')
        );

        return Command::SUCCESS;
    }

    private function dispatch(AMQPMessage $message): void
    {
        $this->line($this->process($message));

        // Always acknowledged: a failure that is worth another try was already published
        // onto a retry-tier queue, and one that is not was already sent to the DLQ. Either
        // way, this delivery is done with -- redelivering it too would only duplicate work.
        $message->ack();
    }

    /**
     * Does the work and says what happened, without printing or touching the channel: it
     * has to be callable with no console attached, and testable with a bare AMQPMessage
     * that was never handed a channel (see AMQPMessage::assertUnacked()).
     */
    public function process(AMQPMessage $message): string
    {
        $priorFailures = $this->priorFailures($message);
        $routingKey = $this->effectiveRoutingKey($message);
        $rawBody = $message->getBody();

        try {
            $data = $this->decode($rawBody);
        } catch (PermanentFailureException $e) {
            return $this->giveUp($routingKey, ['raw_body' => mb_substr($rawBody, 0, 2000)], $priorFailures + 1, $e);
        }

        try {
            $this->attemptFor($routingKey, $data);

            return "Handled {$routingKey}";
        } catch (PermanentFailureException $e) {
            return $this->giveUp($routingKey, $data, $priorFailures + 1, $e);
        } catch (\Throwable $e) {
            $decision = $this->retryPolicy->decide($e, $priorFailures);

            if (!$decision->shouldRetry) {
                return $this->giveUp($routingKey, $data, $priorFailures + 1, $e);
            }

            $attempt = $priorFailures + 1;

            $this->publisher->publishToQueue(self::QUEUE . '.' . $decision->tier, $data, [
                'x-retry-attempt' => $attempt,
                'x-original-routing-key' => $routingKey,
            ]);

            Log::warning('Email delivery failed, retrying', [
                'routing_key' => $routingKey,
                'attempt' => $attempt,
                'tier' => $decision->tier,
                'payload' => $data,
                'error' => $e->getMessage(),
            ]);

            return "Retrying {$routingKey} on {$decision->tier} (attempt {$attempt}): {$e->getMessage()}";
        }
    }

    /**
     * @param array<string, mixed> $data
     * @throws PermanentFailureException when nothing handles this routing key
     */
    private function attemptFor(string $routingKey, array $data): void
    {
        match ($routingKey) {
            self::INVITE_KEY => $this->sendInvite($data['invite_id'] ?? null),
            self::PASSWORD_RESET_KEY => $this->sendPasswordReset($data['invite_id'] ?? null),
            self::NOTIFICATION_KEY => $this->sendNotification($data['notification_id'] ?? null),
            default => throw new PermanentFailureException("No handler for routing key '{$routingKey}'"),
        };
    }

    /**
     * @throws PermanentFailureException when the body is not a JSON object
     * @return array<string, mixed>
     */
    private function decode(string $rawBody): array
    {
        $data = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new PermanentFailureException('The message body is not a valid JSON object: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload what is republished if this is a retry, or kept for the DLQ envelope otherwise
     */
    private function giveUp(string $routingKey, array $payload, int $attempts, \Throwable $error): string
    {
        $this->publisher->publishToQueue(self::QUEUE . '.dlq', [
            'original_routing_key' => $routingKey,
            'payload' => $payload,
            'attempts' => $attempts,
            'error' => mb_substr($error->getMessage(), 0, 500),
            'failed_at' => Carbon::now()->toIso8601String(),
        ]);

        Log::error('Email delivery dead-lettered', [
            'routing_key' => $routingKey,
            'attempts' => $attempts,
            'payload' => $payload,
            'error' => $error->getMessage(),
        ]);

        return "Dead-lettered {$routingKey} after {$attempts} attempts: {$error->getMessage()}";
    }

    /**
     * How many times this message has already failed, before this delivery. Absent on a
     * fresh delivery from delivery.events, since nothing has failed yet.
     */
    private function priorFailures(AMQPMessage $message): int
    {
        return (int) ($this->headers($message)['x-retry-attempt'] ?? 0);
    }

    /**
     * The routing key that decides which handler runs. A message bounced back from a
     * retry-tier queue arrives via the default exchange with its routing key set to the
     * MAIN queue's own name (default-exchange delivery, not the original topic key) -- the
     * header is what carries the real one across that hop.
     */
    private function effectiveRoutingKey(AMQPMessage $message): string
    {
        return (string) ($this->headers($message)['x-original-routing-key'] ?? $message->getRoutingKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function headers(AMQPMessage $message): array
    {
        if (!$message->has('application_headers')) return [];

        return $message->get('application_headers')->getNativeData();
    }

    private function sendInvite($inviteId): void
    {
        if (!$inviteId) return;

        $invite = $this->inviteRepository->findById(id: $inviteId);

        if (!$invite || !$invite->user) return;

        // Already went out on an earlier attempt: a retry must not send it twice.
        if ($invite->email_sent_at) return;

        Mail::to($invite->user->email)->send(new InviteMail($invite));

        $this->inviteRepository->markEmailSent($invite->id);

        Log::info("Invite sent to {$invite->user->email}", ['invite_id' => $invite->id]);
    }

    private function sendPasswordReset($inviteId): void
    {
        if (!$inviteId) return;

        $invite = $this->inviteRepository->findById(id: $inviteId);

        if (!$invite || !$invite->user) return;

        if ($invite->email_sent_at) return;

        Mail::to($invite->user->email)->send(new PasswordResetMail($invite));

        $this->inviteRepository->markEmailSent($invite->id);

        Log::info("Password reset sent to {$invite->user->email}", ['invite_id' => $invite->id]);
    }

    /**
     * Fans out to every recipient of the notification, skipping whoever already got
     * theirs on an earlier attempt. If one recipient's send throws, the loop stops there
     * (the exception propagates to retry the whole message) -- but the ones before it
     * stay marked, so the retry only reaches the recipients still pending.
     */
    private function sendNotification($notificationId): void
    {
        if (!$notificationId) return;

        $notification = $this->notificationRepository->findById($notificationId);

        if (!$notification) return;

        foreach ($this->notificationRepository->usersPendingEmail($notification->id) as $user) {
            Mail::to($user->email)->send(new NotificationMail($notification));

            $this->notificationRepository->markEmailed($notification->id, $user->id);

            Log::info("Notification sent to {$user->email}", ['notification_id' => $notification->id, 'user_id' => $user->id]);
        }
    }
}
