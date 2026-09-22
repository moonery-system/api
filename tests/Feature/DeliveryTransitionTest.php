<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatusEnum;
use App\Models\Delivery;
use App\Models\DeliveryStatus;
use App\Models\User;
use App\Repositories\DeliveryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryTransitionTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $deliveryman;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

        $this->client = $this->client();
        $this->deliveryman = $this->deliveryman();
        $this->admin = $this->admin();
    }

    private function delivery(DeliveryStatusEnum $status = DeliveryStatusEnum::PENDING, bool $assigned = false): Delivery
    {
        $factory = Delivery::factory()->forClient($this->client, $this->admin);

        if ($assigned) $factory = $factory->assignedTo($this->deliveryman);

        return $factory->withStatus($status)->create();
    }

    private function moveTo(Delivery $delivery, string $status, User $actor)
    {
        return $this->actingAsUser($actor)
            ->putJson("/api/deliveries/{$delivery->id}/status", ['status' => $status]);
    }

    public function test_deliveryman_walks_a_delivery_to_delivered(): void
    {
        $delivery = $this->delivery();

        $this->actingAsUser($this->deliveryman)
            ->postJson("/api/deliveries/{$delivery->id}/attach")
            ->assertOk();

        foreach (['picked_up', 'in_transit', 'delivered'] as $status) {
            $this->moveTo($delivery, $status, $this->deliveryman)->assertOk();
        }

        $delivery->refresh();

        $this->assertSame(DeliveryStatusEnum::DELIVERED->value, $delivery->status->name);
        $this->assertNotNull($delivery->delivered_at, 'delivered_at must be stamped on delivery');
        $this->assertSame($this->deliveryman->id, $delivery->delivery_man_id);
    }

    public function test_a_delivered_delivery_cannot_go_back_to_pending(): void
    {
        $delivery = $this->delivery(DeliveryStatusEnum::DELIVERED, assigned: true);

        $this->moveTo($delivery, 'pending', $this->deliveryman)
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    /**
     * @dataProvider finalStatuses
     */
    public function test_a_final_status_has_no_way_out(string $finalStatus): void
    {
        $delivery = $this->delivery(DeliveryStatusEnum::from($finalStatus), assigned: true);

        $this->moveTo($delivery, 'in_transit', $this->admin)->assertStatus(409);
    }

    public static function finalStatuses(): array
    {
        return [
            'delivered' => ['delivered'],
            'canceled by client' => ['canceled_by_client'],
            'canceled by admin' => ['canceled_by_admin'],
            'returned' => ['return_to_sender'],
        ];
    }

    public function test_a_failed_delivery_can_be_retried_and_then_returned(): void
    {
        $delivery = $this->delivery(DeliveryStatusEnum::IN_TRANSIT, assigned: true);

        $this->moveTo($delivery, 'client_address_not_found', $this->deliveryman)->assertOk();
        $this->moveTo($delivery, 'in_transit', $this->deliveryman)->assertOk();
        $this->moveTo($delivery, 'client_address_not_found', $this->deliveryman)->assertOk();
        $this->moveTo($delivery, 'return_to_sender', $this->deliveryman)->assertOk();

        $this->assertSame(
            DeliveryStatusEnum::RETURN_TO_SENDER->value,
            $delivery->refresh()->status->name
        );
    }

    public function test_a_deliveryman_cannot_move_someone_elses_delivery(): void
    {
        $other = $this->deliveryman();
        $delivery = $this->delivery(DeliveryStatusEnum::ATTACHED, assigned: true);

        // Out of the other deliveryman's scope entirely, so 404 rather than 403: a 403
        // would confirm the delivery exists.
        $this->moveTo($delivery, 'picked_up', $other)->assertNotFound();

        $this->assertSame(
            DeliveryStatusEnum::ATTACHED->value,
            $delivery->refresh()->status->name
        );
    }

    public function test_the_client_cancels_before_pickup_but_not_after(): void
    {
        $cancellable = $this->delivery();

        $this->actingAsUser($this->client)
            ->postJson("/api/deliveries/{$cancellable->id}/cancel")
            ->assertOk();

        $this->assertSame(
            DeliveryStatusEnum::CANCELED_BY_CLIENT->value,
            $cancellable->refresh()->status->name
        );

        $inTransit = $this->delivery(DeliveryStatusEnum::IN_TRANSIT, assigned: true);

        $this->actingAsUser($this->client)
            ->postJson("/api/deliveries/{$inTransit->id}/cancel")
            ->assertStatus(409);
    }

    public function test_only_one_deliveryman_wins_the_same_delivery(): void
    {
        $delivery = $this->delivery();
        $other = $this->deliveryman();

        /** @var DeliveryRepository $repository */
        $repository = app(DeliveryRepository::class);

        $pending = DeliveryStatus::where('name', DeliveryStatusEnum::PENDING->value)->value('id');
        $attached = DeliveryStatus::where('name', DeliveryStatusEnum::ATTACHED->value)->value('id');

        // The conditional update is the guard: the second caller must affect no rows.
        $first = $repository->attachDeliveryman($delivery->id, $this->deliveryman->id, $pending, $attached);
        $second = $repository->attachDeliveryman($delivery->id, $other->id, $pending, $attached);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second, 'the second attach must not steal the delivery');
        $this->assertSame($this->deliveryman->id, $delivery->refresh()->delivery_man_id);
    }

    public function test_available_transitions_depend_on_who_is_asking(): void
    {
        $delivery = $this->delivery(DeliveryStatusEnum::IN_TRANSIT, assigned: true);

        $this->actingAsUser($this->deliveryman)
            ->getJson("/api/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.available_transitions', [
                'delivered',
                'client_address_not_found',
                'client_not_found',
            ]);

        // The client can no longer cancel once the package is on its way.
        $this->actingAsUser($this->client)
            ->getJson("/api/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.available_transitions', []);

        $this->actingAsUser($this->admin)
            ->getJson("/api/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('data.available_transitions', ['canceled_by_admin']);
    }
}
