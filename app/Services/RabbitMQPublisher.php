<?php

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitMQPublisher
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

    public function publish(string $routingKey, array $data)
    {
        $this->connect();

        $message = new AMQPMessage(
            json_encode($data),
            ['content_type' => 'application/json', 'delivery_mode' => 2]
        );

        $this->channel->basic_publish(
            $message,
            'delivery.events',
            $routingKey
        );
    }

    /**
     * Publishes straight to a named queue, bypassing delivery.events entirely: the
     * nameless (default) exchange routes a message to the queue whose name equals the
     * routing key, with no binding needed. This is how a message goes onto a retry-tier
     * queue or a dead-letter queue -- both are addressed by name, not by topic.
     *
     * @param array<string, int|string> $headers plain AMQP headers, e.g. x-retry-attempt
     */
    public function publishToQueue(string $queue, array $data, array $headers = []): void
    {
        $this->connect();

        $properties = ['content_type' => 'application/json', 'delivery_mode' => 2];

        if ($headers) {
            $properties['application_headers'] = new AMQPTable($headers);
        }

        $message = new AMQPMessage(json_encode($data), $properties);

        $this->channel->basic_publish($message, '', $queue);
    }

    public function __destruct()
    {
        if ($this->channel) $this->channel->close();
        if ($this->connection) $this->connection->close();
    }
}