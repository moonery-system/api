<?php

namespace Tests\Feature;

use App\Assistant\Llm\ToolCall;
use App\Assistant\Llm\ToolResult;
use App\Assistant\ToolRegistry;
use App\Assistant\Tools\AssistantTool;
use App\Assistant\Tools\ToolContext;
use App\Assistant\Llm\ToolDefinition;
use App\Enums\DeliveryStatusEnum;
use App\Models\AssistantPendingAction;
use App\Models\ClientAddress;
use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\DeliveryItems;
use App\Models\DeliveryStatus;
use App\Models\DeliveryStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tools are exercised through the registry, the same door the model uses.
 */
class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private User $deliveryman;
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();

        $this->alice = $this->client();
        $this->bob = $this->client();
        $this->deliveryman = $this->deliveryman();
        $this->registry = app(ToolRegistry::class);
    }

    private function deliveryOf(User $client, DeliveryStatusEnum $status = DeliveryStatusEnum::PENDING): Delivery
    {
        $delivery = Delivery::factory()->forClient($client, $this->admin())->withStatus($status)->create();

        DeliveryItems::factory()->create(['delivery_id' => $delivery->id, 'name' => 'caixa media']);

        DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'delivery_status_id' => DeliveryStatus::where('name', $status->value)->value('id'),
            'user_id' => $this->deliveryman->id,
        ]);

        return $delivery;
    }

    private function context(User $user): ToolContext
    {
        return new ToolContext($user, Conversation::firstOrCreate(['user_id' => $user->id]));
    }

    private function callTool(User $as, string $tool, array $arguments = []): ToolResult
    {
        return $this->registry->execute(new ToolCall('call-1', $tool, $arguments), $this->context($as));
    }

    // ---- scope: another customer's data is unreachable ---------------------------------

    public function test_a_delivery_of_another_customer_answers_exactly_like_one_that_does_not_exist(): void
    {
        $bobs = $this->deliveryOf($this->bob);

        $foreign = $this->callTool($this->alice, 'get_delivery', ['delivery_id' => $bobs->id]);
        $missing = $this->callTool($this->alice, 'get_delivery', ['delivery_id' => 999999]);

        $this->assertSame($missing->content, $foreign->content);
        $this->assertFalse($foreign->content['found']);
        $this->assertStringNotContainsString($bobs->tracking_code, json_encode($foreign->content));
    }

    public function test_the_tracking_code_of_another_customer_is_not_found_either(): void
    {
        $bobs = $this->deliveryOf($this->bob);

        $byCode = $this->callTool($this->alice, 'get_delivery', ['tracking_code' => $bobs->tracking_code]);

        $this->assertFalse($byCode->content['found']);
    }

    public function test_the_owner_finds_the_same_delivery_by_id_and_by_tracking_code(): void
    {
        $mine = $this->deliveryOf($this->alice);

        $byId = $this->callTool($this->alice, 'get_delivery', ['delivery_id' => $mine->id]);
        // The lookup tolerates case and spaces around the code.
        $byCode = $this->callTool($this->alice, 'get_delivery', ['tracking_code' => ' ' . strtolower($mine->tracking_code) . ' ']);

        $this->assertTrue($byId->content['found']);
        $this->assertSame($byId->content, $byCode->content);
    }

    public function test_a_forged_client_id_is_ignored_by_every_tool(): void
    {
        $mine = $this->deliveryOf($this->alice);
        $bobs = $this->deliveryOf($this->bob);

        // The model was told (or tricked) to act as Bob.
        $forged = ['client_id' => $this->bob->id, 'user_id' => $this->bob->id, 'customer' => $this->bob->email];

        $list = $this->callTool($this->alice, 'list_my_deliveries', $forged);
        $this->assertSame([$mine->tracking_code], array_column($list->content['deliveries'], 'tracking_code'));

        $get = $this->callTool($this->alice, 'get_delivery', ['delivery_id' => $bobs->id] + $forged);
        $this->assertFalse($get->content['found']);

        $can = $this->callTool($this->alice, 'can_cancel_delivery', ['delivery_id' => $bobs->id] + $forged);
        $this->assertFalse($can->content['found']);

        $request = $this->callTool($this->alice, 'request_cancel_delivery', ['delivery_id' => $bobs->id] + $forged);
        $this->assertFalse($request->content['found']);
        $this->assertSame(0, AssistantPendingAction::count());
    }

    public function test_the_list_only_has_the_deliveries_of_the_customer_and_filters_by_status(): void
    {
        $pending = $this->deliveryOf($this->alice);
        $this->deliveryOf($this->alice, DeliveryStatusEnum::IN_TRANSIT);
        $this->deliveryOf($this->bob);

        $all = $this->callTool($this->alice, 'list_my_deliveries');
        $this->assertSame(2, $all->content['count']);

        $onlyPending = $this->callTool($this->alice, 'list_my_deliveries', ['status' => 'pending']);
        $this->assertSame([$pending->tracking_code], array_column($onlyPending->content['deliveries'], 'tracking_code'));
    }

    public function test_the_list_refuses_a_limit_above_the_maximum(): void
    {
        $result = $this->callTool($this->alice, 'list_my_deliveries', ['limit' => 500]);

        $this->assertTrue($result->isError);
    }

    // ---- what the provider gets to see -------------------------------------------------

    public function test_the_detail_leaves_out_personal_data_and_the_delivery_man(): void
    {
        $address = ClientAddress::factory()->for($this->alice)->create([
            'complement' => 'APTO-SECRETO-999',
            'zip_code' => 'CEP-SECRETO-000',
        ]);

        $delivery = Delivery::factory()->forClient($this->alice, $this->admin())->assignedTo($this->deliveryman)->create();
        $delivery->update(['client_address_id' => $address->id]);
        DeliveryStatusHistory::create([
            'delivery_id' => $delivery->id,
            'delivery_status_id' => $delivery->delivery_status_id,
            'user_id' => $this->deliveryman->id,
        ]);

        $json = json_encode($this->callTool($this->alice, 'get_delivery', ['delivery_id' => $delivery->id])->content);

        foreach ([
            $this->deliveryman->name, $this->deliveryman->email,
            $this->alice->email,
            'APTO-SECRETO-999', 'CEP-SECRETO-000',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }

        $this->assertStringContainsString($address->address_line, $json);
        $this->assertStringContainsString('attached', $json);
    }

    public function test_the_detail_has_the_history_the_dates_and_the_items(): void
    {
        $delivery = $this->deliveryOf($this->alice, DeliveryStatusEnum::DELIVERED);
        $delivery->update(['delivered_at' => '2026-09-20 10:00:00']);

        $content = $this->callTool($this->alice, 'get_delivery', ['delivery_id' => $delivery->id])->content;

        $this->assertSame('delivered', $content['status']);
        $this->assertSame('2026-09-20 10:00:00', $content['delivered_at']);
        $this->assertArrayHasKey('scheduled_to', $content);
        $this->assertSame('caixa media', $content['items'][0]['name']);
        $this->assertSame('delivered', $content['history'][0]['status']);
        $this->assertArrayNotHasKey('user', $content['history'][0]);
    }

    // ---- cancelling ---------------------------------------------------------------------

    public function test_can_cancel_follows_the_state_machine(): void
    {
        foreach ([
            [DeliveryStatusEnum::PENDING, true],
            [DeliveryStatusEnum::ATTACHED, true],
            [DeliveryStatusEnum::PICKED_UP, false],
            [DeliveryStatusEnum::IN_TRANSIT, false],
            [DeliveryStatusEnum::DELIVERED, false],
        ] as [$status, $expected]) {
            $delivery = $this->deliveryOf($this->alice, $status);

            $result = $this->callTool($this->alice, 'can_cancel_delivery', ['delivery_id' => $delivery->id]);

            $this->assertSame($expected, $result->content['can_cancel'], "status {$status->value}");
            $this->assertSame($expected, $result->content['reason'] === null);
        }
    }

    public function test_requesting_a_cancellation_only_leaves_a_confirmation_waiting(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $context = $this->context($this->alice);

        $result = $this->registry->execute(
            new ToolCall('c', 'request_cancel_delivery', ['delivery_id' => $delivery->id]),
            $context
        );

        $this->assertTrue($result->content['awaiting_confirmation']);

        // The delivery is untouched.
        $this->assertSame('pending', $delivery->fresh()->status->name);

        $action = AssistantPendingAction::firstOrFail();
        $this->assertSame(AssistantPendingAction::STATUS_PENDING, $action->status);
        $this->assertSame($delivery->id, $action->delivery_id);
        $this->assertSame($this->alice->id, $action->user_id);
        $this->assertTrue($action->expires_at->isFuture());

        $this->assertSame($action->id, $context->pendingActionId);
        $this->assertSame($delivery->tracking_code, $context->pendingTrackingCode);
    }

    public function test_a_delivery_that_cannot_be_canceled_gets_no_confirmation(): void
    {
        $delivery = $this->deliveryOf($this->alice, DeliveryStatusEnum::IN_TRANSIT);
        $context = $this->context($this->alice);

        $result = $this->registry->execute(
            new ToolCall('c', 'request_cancel_delivery', ['delivery_id' => $delivery->id]),
            $context
        );

        $this->assertFalse($result->content['awaiting_confirmation']);
        $this->assertSame(0, AssistantPendingAction::count());
        $this->assertNull($context->pendingActionId);
    }

    public function test_a_new_request_replaces_the_previous_one_for_the_same_delivery(): void
    {
        $delivery = $this->deliveryOf($this->alice);

        $this->callTool($this->alice, 'request_cancel_delivery', ['delivery_id' => $delivery->id]);
        $this->callTool($this->alice, 'request_cancel_delivery', ['delivery_id' => $delivery->id]);

        $this->assertSame(1, AssistantPendingAction::where('status', AssistantPendingAction::STATUS_PENDING)->count());
        $this->assertSame(1, AssistantPendingAction::where('status', AssistantPendingAction::STATUS_SUPERSEDED)->count());
    }

    // ---- handoff and the registry itself -----------------------------------------------

    public function test_the_handoff_tool_only_records_the_wish(): void
    {
        $context = $this->context($this->alice);

        $this->registry->execute(new ToolCall('c', 'handoff_to_support', ['reason' => 'quer falar com uma pessoa']), $context);

        $this->assertTrue($context->handoffRequested());
        $this->assertSame('quer falar com uma pessoa', $context->handoffNote);
        $this->assertSame(Conversation::ASSISTANT_ACTIVE, $context->conversation->fresh()->assistant_status);
    }

    public function test_an_unknown_tool_and_bad_arguments_come_back_as_errors(): void
    {
        $this->assertTrue($this->callTool($this->alice, 'drop_database')->isError);
        $this->assertTrue($this->callTool($this->alice, 'get_delivery', [])->isError);
        $this->assertTrue($this->callTool($this->alice, 'can_cancel_delivery', ['delivery_id' => 'abc'])->isError);
    }

    public function test_a_crashing_tool_never_leaks_its_message_nor_escapes(): void
    {
        $crashing = new class implements AssistantTool {
            public function definition(): ToolDefinition
            {
                return new ToolDefinition('crash', 'always fails');
            }

            public function execute(ToolContext $context, array $arguments): array
            {
                throw new \RuntimeException('SQLSTATE[42P01]: relation "secret_table" does not exist');
            }
        };

        $result = (new ToolRegistry([$crashing]))->execute(new ToolCall('c', 'crash'), $this->context($this->alice));

        $this->assertTrue($result->isError);
        $this->assertStringNotContainsString('secret_table', json_encode($result->content));
    }

    public function test_an_oversized_result_is_not_handed_to_the_model(): void
    {
        $huge = new class implements AssistantTool {
            public function definition(): ToolDefinition
            {
                return new ToolDefinition('huge', 'returns too much');
            }

            public function execute(ToolContext $context, array $arguments): array
            {
                return ['blob' => str_repeat('x', 50000)];
            }
        };

        $result = (new ToolRegistry([$huge]))->execute(new ToolCall('c', 'huge'), $this->context($this->alice));

        $this->assertTrue($result->isError);
        $this->assertArrayNotHasKey('blob', $result->content);
    }

    public function test_the_registry_exposes_the_five_tools(): void
    {
        $names = array_map(fn($definition) => $definition->name, $this->registry->definitions());

        $this->assertEqualsCanonicalizing(
            ['list_my_deliveries', 'get_delivery', 'can_cancel_delivery', 'request_cancel_delivery', 'handoff_to_support'],
            $names
        );
    }
}
