<?php

namespace App\Console\Commands;

use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\NotificationInterface;
use App\Mail\InviteMail;
use App\Mail\NotificationMail;
use App\Services\RabbitMQConsumer;
use Illuminate\Console\Command;
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

    private const NOTIFICATION_KEY = 'notifications.email';

    public function __construct(
        private RabbitMQConsumer $consumer,

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
            [self::INVITE_KEY, self::NOTIFICATION_KEY],
            fn(AMQPMessage $message) => $this->dispatch($message)
        );

        return Command::SUCCESS;
    }

    private function dispatch(AMQPMessage $message): void
    {
        $routingKey = $message->getRoutingKey();
        $data = json_decode($message->getBody(), true) ?? [];

        try {
            match ($routingKey) {
                self::INVITE_KEY => $this->sendInvite($data['invite_id'] ?? null),
                self::NOTIFICATION_KEY => $this->sendNotification($data['notification_id'] ?? null),
                default => $this->warn("No handler for routing key {$routingKey}"),
            };
        } catch (\Throwable $e) {
            $this->error("Failed to handle {$routingKey}: {$e->getMessage()}");
        }

        $message->ack();
    }

    private function sendInvite($inviteId): void
    {
        if (!$inviteId) return;

        $invite = $this->inviteRepository->findById(id: $inviteId);

        if (!$invite || !$invite->user) return;

        Mail::to($invite->user->email)->send(new InviteMail($invite));

        $this->info("Invite sent to {$invite->user->email}");
    }

    private function sendNotification($notificationId): void
    {
        if (!$notificationId) return;

        $notification = $this->notificationRepository->findById(id: $notificationId);

        if (!$notification) return;

        foreach ($notification->users as $user) {
            Mail::to($user->email)->send(new NotificationMail($notification));

            $this->info("Notification {$notification->id} sent to {$user->email}");
        }
    }
}
