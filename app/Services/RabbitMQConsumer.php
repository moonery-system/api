<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConsumer
{
    protected ?AMQPStreamConnection $connection = null;
    protected ?AMQPChannel $channel = null;

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

        $this->channel->exchange_declare(
            'delivery.events',
            'topic',
            false,
            true,
            false
        );
    }

    public function consume(string $queue, array $routingKeys, callable $handler, array $retryTiersMs = []): void
    {
        $this->connect();

        if ($retryTiersMs) $this->declareRetryLadder($queue, $retryTiersMs);

        $this->channel->queue_declare($queue, false, true, false, false);

        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queue, 'delivery.events', $routingKey);
        }

        // prefetch_size 0 = sem limite de bytes; o que limita e o prefetch_count.
        $this->channel->basic_qos(0, 1, false);

        $this->channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            $handler
        );

        while ($this->channel->is_consuming()) {
            $this->channel->wait();
        }
    }

    /**
     * Declares the queues a retry ladder needs for one main queue: one waiting room per
     * tier (fixed TTL, so a single queue never mixes tiers -- RabbitMQ only checks
     * per-message TTL at the head of a queue, so a long-TTL message ahead of a short-TTL
     * one would block the short one from expiring on time) and the terminal DLQ.
     *
     * None of these queues ever has a consumer attached: the tiers exist only to be
     * timed out and dead-lettered back onto $queue via the default exchange, and the DLQ
     * is read only by the customs:dlq:* commands.
     *
     * Declaring the same names with the same arguments on every boot is safe and
     * idempotent; changing a tier's arguments later would need a new name, the same as
     * any other queue (see RabbitMQConsumer::consume()).
     *
     * @param array<int, int> $tiersMs delay in milliseconds for each tier, in order
     */
    public function declareRetryLadder(string $queue, array $tiersMs): void
    {
        $this->connect();

        foreach ($tiersMs as $index => $delayMs) {
            $tier = $index + 1;

            $this->channel->queue_declare("{$queue}.retry.{$tier}", false, true, false, false, false, new AMQPTable([
                'x-message-ttl' => $delayMs,
                'x-dead-letter-exchange' => '',
                'x-dead-letter-routing-key' => $queue,
            ]));
        }

        $this->channel->queue_declare("{$queue}.dlq", false, true, false, false);
    }

    public function close(): void
    {
        if ($this->channel) $this->channel->close();
        if ($this->connection) $this->connection->close();
    }
}
