<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
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

    public function consume(string $queue, array $routingKeys, callable $handler): void
    {
        $this->connect();

        $this->channel->queue_declare($queue, false, true, false, false);

        foreach ($routingKeys as $routingKey) {
            $this->channel->queue_bind($queue, 'delivery.events', $routingKey);
        }

        $this->channel->basic_qos(null, 1, false);

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

    public function close(): void
    {
        if ($this->channel) $this->channel->close();
        if ($this->connection) $this->connection->close();
    }
}
