<?php

namespace Database\Factories;

use App\Enums\DeliveryStatusEnum;
use App\Models\ClientAddress;
use App\Models\Delivery;
use App\Models\DeliveryStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    public function definition()
    {
        return [
            'tracking_code' => 'MNY-TEST-' . strtoupper(Str::random(6)),
            'delivery_status_id' => fn() => $this->statusId(DeliveryStatusEnum::PENDING->value),
        ];
    }

    /**
     * Wires creator, client and an address that really belongs to that client -- the
     * API refuses any other combination, so the factory should not be able to build it.
     */
    public function forClient(User $client, ?User $creator = null): static
    {
        return $this->state(function () use ($client, $creator) {
            $address = ClientAddress::factory()->for($client)->create();

            return [
                'creator_id' => ($creator ?? User::factory()->create())->id,
                'client_id' => $client->id,
                'client_address_id' => $address->id,
            ];
        });
    }

    public function withStatus(DeliveryStatusEnum $status): static
    {
        return $this->state(fn() => ['delivery_status_id' => $this->statusId($status->value)]);
    }

    public function assignedTo(User $deliveryman): static
    {
        return $this->state(fn() => [
            'delivery_man_id' => $deliveryman->id,
            'delivery_status_id' => $this->statusId(DeliveryStatusEnum::ATTACHED->value),
        ]);
    }

    private function statusId(string $name): int
    {
        return DeliveryStatus::where('name', $name)->value('id');
    }
}
