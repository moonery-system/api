<?php

namespace App\Services;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMQPublisher
{
    protected $connection;
    protected $channel;

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

    public function publish(string $routingKey, array $data)
    {
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

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }
}