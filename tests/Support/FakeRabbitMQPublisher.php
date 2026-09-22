<?php

namespace Tests\Support;

use App\Services\RabbitMQPublisher;

/**
 * Keeps the suite from opening an AMQP connection: the broker is not part of what
 * these tests are checking, and waiting for a connection timeout on every status
 * change would make them crawl.
 */
class FakeRabbitMQPublisher extends RabbitMQPublisher
{
    /** @var array<int, array{routingKey: string, data: array}> */
    public array $published = [];

    public function publish(string $routingKey, array $data)
    {
        $this->published[] = ['routingKey' => $routingKey, 'data' => $data];
    }
}
