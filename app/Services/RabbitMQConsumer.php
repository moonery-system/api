<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitMQConsumer
{
    protected AMQPStreamConnection $connection;
    protected AMQPChannel $channel;

    public function __construct()
    {
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
        $this->channel->close();
        $this->connection->close();
    }
}
