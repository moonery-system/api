<?php

namespace App\Console\Commands;

use App\Assistant\AssistantDispatcher;
use App\Assistant\AssistantRunner;
use App\Messaging\PermanentFailureException;
use App\Messaging\RetryPolicy;
use App\Services\RabbitMQConsumer;
use App\Services\RabbitMQPublisher;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Message\AMQPMessage;

class ConsumeAssistantQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customs:consume-assistant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for consuming the assistant queue and answering the customers messages';

    /**
     * Its own queue, never shared with the e-mails or the websocket ones: a customer message
     * must trigger the assistant and nothing else.
     */
    private const QUEUE = 'assistant.queue';

    public function __construct(
        private RabbitMQConsumer $consumer,
        private RabbitMQPublisher $publisher,
        private RetryPolicy $retryPolicy,
        private AssistantRunner $runner
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * One consumer, one message at a time (prefetch 1): that is also what keeps the calls
     * to the provider spaced without any lock.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Waiting for messages on ' . self::QUEUE . '...');

        $this->consumer->consume(
            self::QUEUE,
            [AssistantDispatcher::ROUTING_KEY],
            fn(AMQPMessage $message) => $this->dispatch($message),
            config('messaging.retry.tiers_ms')
        );

        return Command::SUCCESS;
    }

    private function dispatch(AMQPMessage $message): void
    {
        $data = json_decode($message->getBody(), true);

        $outcome = $this->process(is_array($data) ? $data : [], $this->priorFailures($message));

        $this->line($outcome);

        // Always acknowledged: a retry was already published onto a retry-tier queue, a
        // give-up was already sent to the DLQ, and a run that completed (fallback included)
        // needs nothing more -- redelivering any of these would only answer the same
        // message again.
        $message->ack();
    }

    /**
     * Does the work and says what happened, without printing or touching the channel: it
     * has to be callable with no console attached.
     *
     * Almost every failure the assistant can have is already absorbed inside
     * AssistantRunner itself (it ends in a fallback message and a handoff, never an
     * exception). What can still reach here is what happens BEFORE that -- claiming the
     * run, loading the message, the conversation, the bot -- typically a database blip.
     * Since claiming is idempotent (message_id is unique), retrying is safe; this is a
     * safety net for infrastructure, not a fix for an observed bug.
     *
     * @param array<string, mixed> $data the payload: {message_id}
     */
    public function process(array $data, int $priorFailures = 0): string
    {
        $messageId = $data['message_id'] ?? null;

        if (!is_numeric($messageId)) {
            $this->giveUp($data, $priorFailures + 1, new PermanentFailureException('Payload has no message_id'));

            return 'Ignoring a payload without message_id';
        }

        try {
            $this->runner->handle((int) $messageId);

            return "Handled message {$messageId}";
        } catch (\Throwable $e) {
            Log::error('The assistant consumer failed', [
                'message_id' => $messageId,
                'attempt' => $priorFailures + 1,
                'message' => $e->getMessage(),
            ]);

            // A long-lived process: if the database connection is what broke, the next
            // message must not inherit it.
            DB::reconnect();

            $decision = $this->retryPolicy->decide($e, $priorFailures);

            if (!$decision->shouldRetry) {
                $this->giveUp($data, $priorFailures + 1, $e);

                return "Dead-lettered message {$messageId} after {$priorFailures} retries: " . $e::class;
            }

            $attempt = $priorFailures + 1;

            $this->publisher->publishToQueue(self::QUEUE . '.' . $decision->tier, $data, [
                'x-retry-attempt' => $attempt,
            ]);

            return "Retrying message {$messageId} on {$decision->tier} (attempt {$attempt}): " . $e::class;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function giveUp(array $payload, int $attempts, \Throwable $error): void
    {
        $this->publisher->publishToQueue(self::QUEUE . '.dlq', [
            'payload' => $payload,
            'attempts' => $attempts,
            'error' => mb_substr($error->getMessage(), 0, 500),
            'failed_at' => Carbon::now()->toIso8601String(),
        ]);

        Log::error('Assistant message dead-lettered', [
            'payload' => $payload,
            'attempts' => $attempts,
            'error' => $error->getMessage(),
        ]);
    }

    /**
     * How many times this message has already failed, before this delivery. Public (and
     * separate from process()) so the header-reading itself -- easy to get backwards, since
     * the header is only present on a message bounced back from a retry-tier queue -- is
     * testable on its own with a bare AMQPMessage, with no channel and no console attached.
     */
    public function priorFailures(AMQPMessage $message): int
    {
        if (!$message->has('application_headers')) return 0;

        return (int) ($message->get('application_headers')->getNativeData()['x-retry-attempt'] ?? 0);
    }
}
