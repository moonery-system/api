<?php

namespace Tests\Feature;

use App\Enums\DeliveryStatusEnum;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

        $this->admin = $this->admin();
    }

    private function deliveryFor(User $client, ?User $deliveryman = null): Delivery
    {
        $factory = Delivery::factory()->forClient($client, $this->admin);

        return $deliveryman
            ? $factory->assignedTo($deliveryman)->create()
            : $factory->create();
    }

    /** @return array<int, int> */
    private function listedIds(User $actor): array
    {
        $response = $this->actingAsUser($actor)->getJson('/api/deliveries')->assertOk();

        return array_column($response->json('data.data'), 'id');
    }

    public function test_a_client_only_sees_his_own_deliveries(): void
    {
        $client = $this->client();
        $other = $this->client();

        $mine = $this->deliveryFor($client);
        $notMine = $this->deliveryFor($other);

        $listed = $this->listedIds($client);

        $this->assertContains($mine->id, $listed);
        $this->assertNotContains($notMine->id, $listed, 'another client delivery leaked into the list');
    }

    public function test_a_deliveryman_sees_his_own_plus_the_free_pool(): void
    {
        $client = $this->client();
        $deliveryman = $this->deliveryman();
        $another = $this->deliveryman();

        $mine = $this->deliveryFor($client, $deliveryman);
        $free = $this->deliveryFor($client);
        $someoneElses = $this->deliveryFor($client, $another);

        $listed = $this->listedIds($deliveryman);

        $this->assertContains($mine->id, $listed, 'his own delivery must be listed');
        $this->assertContains($free->id, $listed, 'the free pool must be listed');
        $this->assertNotContains($someoneElses->id, $listed, 'another deliveryman delivery leaked');
    }

    public function test_an_admin_sees_everything(): void
    {
        $client = $this->client();
        $other = $this->client();

        $first = $this->deliveryFor($client);
        $second = $this->deliveryFor($other);

        $listed = $this->listedIds($this->admin);

        $this->assertContains($first->id, $listed);
        $this->assertContains($second->id, $listed);
    }

    public function test_a_delivery_out_of_scope_answers_not_found_rather_than_forbidden(): void
    {
        $client = $this->client();
        $other = $this->client();

        $notMine = $this->deliveryFor($other);

        // 404 on purpose: a 403 would confirm the delivery exists.
        $this->actingAsUser($client)
            ->getJson("/api/deliveries/{$notMine->id}")
            ->assertNotFound();
    }

    public function test_a_deliveryman_cannot_see_a_delivery_taken_by_another(): void
    {
        $client = $this->client();
        $deliveryman = $this->deliveryman();
        $another = $this->deliveryman();

        $taken = $this->deliveryFor($client, $another);

        $this->actingAsUser($deliveryman)
            ->getJson("/api/deliveries/{$taken->id}")
            ->assertNotFound();
    }

    public function test_the_permission_middleware_blocks_before_the_service_runs(): void
    {
        $client = $this->client();
        $delivery = $this->deliveryFor($client);

        // The client has no deliveries.update, so can: refuses it at the route.
        $this->actingAsUser($client)
            ->putJson("/api/deliveries/{$delivery->id}/status", ['status' => 'picked_up'])
            ->assertForbidden();

        // And a deliveryman cannot create one: no deliveries.create.
        $this->actingAsUser($this->deliveryman())
            ->postJson('/api/deliveries', [
                'client_id' => $client->id,
                'client_address_id' => $delivery->client_address_id,
                'items' => [['name' => 'caixa de teste', 'quantity' => 1, 'weight' => 1.0]],
            ])
            ->assertForbidden();
    }

    public function test_a_client_cannot_assign_a_deliveryman(): void
    {
        $client = $this->client();
        $deliveryman = $this->deliveryman();
        $delivery = $this->deliveryFor($client);

        $this->actingAsUser($client)
            ->putJson("/api/deliveries/{$delivery->id}/deliveryman", [
                'delivery_man_id' => $deliveryman->id,
            ])
            ->assertForbidden();

        $this->assertNull($delivery->refresh()->delivery_man_id);
    }

    public function test_the_delivery_address_must_belong_to_the_delivery_client(): void
    {
        $client = $this->client();
        $other = $this->client();

        $otherDelivery = $this->deliveryFor($other);

        $this->actingAsUser($this->admin)
            ->postJson('/api/deliveries', [
                'client_id' => $client->id,
                'client_address_id' => $otherDelivery->client_address_id,
                'items' => [['name' => 'caixa de teste', 'quantity' => 1, 'weight' => 1.0]],
            ])
            ->assertForbidden();
    }

    public function test_status_history_records_every_step_including_pickup(): void
    {
        $client = $this->client();
        $deliveryman = $this->deliveryman();
        $delivery = $this->deliveryFor($client);

        $this->actingAsUser($deliveryman)->postJson("/api/deliveries/{$delivery->id}/attach")->assertOk();
        $this->actingAsUser($deliveryman)->deleteJson("/api/deliveries/{$delivery->id}/attach")->assertOk();
        $this->actingAsUser($deliveryman)->postJson("/api/deliveries/{$delivery->id}/attach")->assertOk();

        $recorded = $this->actingAsUser($this->admin)
            ->getJson("/api/deliveries/{$delivery->id}")
            ->assertOk()
            ->json('data.status_history');

        // attach and detach change the status with a query builder update, which fires
        // no model events -- this is what would be missing if history came from logs.
        $this->assertSame(
            [
                DeliveryStatusEnum::ATTACHED->value,
                DeliveryStatusEnum::PENDING->value,
                DeliveryStatusEnum::ATTACHED->value,
            ],
            array_column(array_column($recorded, 'status'), 'name')
        );
    }
}
