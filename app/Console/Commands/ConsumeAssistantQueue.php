<?php

namespace App\Console\Commands;

use App\Assistant\AssistantDispatcher;
use App\Assistant\AssistantRunner;
use App\Services\RabbitMQConsumer;
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
            fn(AMQPMessage $message) => $this->dispatch($message)
        );

        return Command::SUCCESS;
    }

    private function dispatch(AMQPMessage $message): void
    {
        $data = json_decode($message->getBody(), true);

        $outcome = $this->process(is_array($data) ? $data : []);

        $this->line($outcome);

        // Always acknowledged: a failure inside the run already ended in a fallback and a
        // handoff, and redelivering would only answer the same message again.
        $message->ack();
    }

    /**
     * Does the work and says what happened, without printing: it has to be callable with no
     * console attached.
     *
     * @param array<string, mixed> $data the payload: {message_id}
     */
    public function process(array $data): string
    {
        $messageId = $data['message_id'] ?? null;

        if (!is_numeric($messageId)) return 'Ignoring a payload without message_id';

        try {
            $this->runner->handle((int) $messageId);

            return "Handled message {$messageId}";
        } catch (\Throwable $e) {
            Log::error('The assistant consumer failed', ['message_id' => $messageId, 'message' => $e->getMessage()]);

            // A long-lived process: if the database connection is what broke, the next
            // message must not inherit it.
            DB::reconnect();

            return "Failed to handle message {$messageId}: " . $e::class;
        }
    }
}
