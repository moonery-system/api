<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatusEnum;
use App\Exceptions\BusinessException;
use App\Models\Delivery;
use App\Services\DeliveryTransitionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
    }

    private function deliveryFor($client, DeliveryStatusEnum $status): Delivery
    {
        return Delivery::factory()
            ->forClient($client, $this->admin())
            ->withStatus($status)
            ->create()
            ->load('status');
    }

    public function test_the_check_is_null_when_the_client_may_cancel(): void
    {
        $client = $this->client();
        $delivery = $this->deliveryFor($client, DeliveryStatusEnum::PENDING);

        $this->assertNull(
            app(DeliveryTransitionValidator::class)
                ->checkTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $client)
        );
    }

    public function test_the_check_gives_the_same_reason_the_assertion_throws(): void
    {
        $client = $this->client();
        $validator = app(DeliveryTransitionValidator::class);

        foreach ([DeliveryStatusEnum::PICKED_UP, DeliveryStatusEnum::IN_TRANSIT, DeliveryStatusEnum::DELIVERED] as $status) {
            $delivery = $this->deliveryFor($client, $status);

            $reason = $validator->checkTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $client);

            $this->assertNotNull($reason, "{$status->value} must not be cancellable by the client");

            try {
                $validator->assertCanTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $client);
                $this->fail('assertCanTransition should have thrown');
            } catch (BusinessException $e) {
                $this->assertSame($reason, $e->getMessage());
            }
        }
    }

    public function test_the_check_refuses_a_delivery_of_another_client(): void
    {
        $owner = $this->client();
        $other = $this->client();
        $delivery = $this->deliveryFor($owner, DeliveryStatusEnum::PENDING);

        $this->assertNotNull(
            app(DeliveryTransitionValidator::class)
                ->checkTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $other)
        );
    }
}
