<?php

namespace App\Messaging;

use App\Assistant\AssistantDispatcher;
use App\Services\RabbitMQPublisher;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Reads and replays a dead-letter queue. Every queue this project retries is known by a
 * short name, so an operator types "emails", not "emails.queue.dlq".
 *
 * basic_get requires a live channel, so none of this is unit-testable -- it is verified by
 * hand against the real broker (see docs/messaging-decision-log.md).
 */
class DlqInspector
{
    /**
     * @var array<string, array{queue: string, default_routing_key: ?string}>
     */
    private const QUEUES = [
        // Every routing key a message can carry to emails.queue is already inside the DLQ
        // envelope itself (original_routing_key), so nothing fixed is needed here.
        'emails' => ['queue' => 'emails.queue', 'default_routing_key' => null],
        // assistant.queue only ever has one routing key; the DLQ envelope does not carry
        // one at all, so replaying always targets it directly.
        'assistant' => ['queue' => 'assistant.queue', 'default_routing_key' => AssistantDispatcher::ROUTING_KEY],
    ];

    protected ?AMQPStreamConnection $connection = null;
    protected ?AMQPChannel $channel = null;

    /**
     * @return array{queue: string, dlq: string, default_routing_key: ?string}
     * @throws \InvalidArgumentException for a name that is not one of the known queues
     */
    public static function resolve(string $shortName): array
    {
        if (!isset(self::QUEUES[$shortName])) {
            $known = implode(', ', array_keys(self::QUEUES));

            throw new \InvalidArgumentException("Unknown queue '{$shortName}'. Known queues: {$known}.");
        }

        $entry = self::QUEUES[$shortName];

        return $entry + ['dlq' => $entry['queue'] . '.dlq'];
    }

    private function connect(): void
    {
        if ($this->channel) return;

        $this->connection = new AMQPStreamConnection(
            env('RABBITMQ_HOST', 'localhost'),
            env('RABBITMQ_PORT', 5672),
            env('RABBITMQ_USER', 'guest'),
            env('RABBITMQ_PASSWORD', 'guest')
        );

        $this->channel = $this->connection->channel();
    }

    /**
     * How many messages are waiting, without touching any of them: a passive declare only
     * asks the broker what it already knows.
     */
    public function depth(string $queue): int
    {
        $this->connect();

        [, $messageCount] = $this->channel->queue_declare($queue, true);

        return $messageCount;
    }

    /**
     * Reads up to $limit messages without removing any of them.
     *
     * All of them are fetched first, before any is put back: basic_get never redelivers a
     * message still awaiting ack on the same channel, so this is what keeps the loop from
     * fetching the SAME message over and over when the queue has fewer than $limit in it
     * (nacking one back to the front immediately, then asking again, would do exactly
     * that). They are nacked back in reverse, so the original order ends up at the front
     * again, and listing never drains the queue.
     *
     * @return array<int, array<string, mixed>> the decoded envelope of each message
     */
    public function peek(string $queue, int $limit): array
    {
        $this->connect();

        $held = $this->take($queue, $limit);

        foreach (array_reverse($held) as $message) {
            $message->nack(true);
        }

        return array_map(fn($message) => $this->decode($message), $held);
    }

    /**
     * Takes up to $limit messages, republishing each one's original payload back onto
     * delivery.events (a fresh attempt cycle, with no retry-attempt header at all) and only
     * then removing it from the DLQ. A republish failure -- or an envelope too broken to
     * read -- puts the message back with requeue rather than losing it.
     *
     * All of them are fetched first (see peek() for why): a malformed entry nacked back
     * mid-loop would otherwise be picked up again by the very next basic_get in the same
     * run, and a single bad entry would spin forever instead of the rest ever being reached.
     *
     * @return array<int, string> one human-readable outcome per message handled
     */
    public function replay(string $queue, int $limit, RabbitMQPublisher $publisher, ?string $fixedRoutingKey): array
    {
        $this->connect();

        $outcomes = [];

        foreach ($this->take($queue, $limit) as $message) {
            $envelope = $this->decode($message);
            $routingKey = $fixedRoutingKey ?? ($envelope['original_routing_key'] ?? null);
            $payload = $envelope['payload'] ?? null;

            if (!$routingKey || !is_array($payload)) {
                $message->nack(true);
                $outcomes[] = 'Skipped one malformed entry (kept in the queue).';
                continue;
            }

            try {
                $publisher->publish($routingKey, $payload);
                $message->ack();
                $outcomes[] = "Replayed onto '{$routingKey}'.";
            } catch (\Throwable $e) {
                $message->nack(true);
                $outcomes[] = "Failed to replay onto '{$routingKey}': {$e->getMessage()} (kept in the queue).";
            }
        }

        return $outcomes;
    }

    /**
     * Fetches up to $limit DISTINCT messages: each basic_get holds its message pending ack,
     * so the next one always returns a different message (or none, once the queue is empty
     * of anything not already held).
     *
     * @return array<int, \PhpAmqpLib\Message\AMQPMessage>
     */
    private function take(string $queue, int $limit): array
    {
        $held = [];

        for ($i = 0; $i < $limit; $i++) {
            $message = $this->channel->basic_get($queue, false);

            if (!$message) break;

            $held[] = $message;
        }

        return $held;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\PhpAmqpLib\Message\AMQPMessage $message): array
    {
        return json_decode($message->getBody(), true) ?? ['_raw' => $message->getBody()];
    }
}
