<?php

namespace Tests\Feature;

use App\Assistant\AssistantRunner;
use App\Assistant\Llm\LlmClient;
use App\Enums\DeliveryStatusEnum;
use App\Models\AssistantPendingAction;
use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class AssistantConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;
    private User $bob;
    private User $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

        $this->alice = $this->client();
        $this->bob = $this->client();
        $this->bot = User::where('email', config('assistant.bot_email'))->firstOrFail();
    }

    private function deliveryOf(User $client, DeliveryStatusEnum $status = DeliveryStatusEnum::PENDING): Delivery
    {
        return Delivery::factory()->forClient($client, $this->admin())->withStatus($status)->create();
    }

    private function pendingFor(User $user, Delivery $delivery, array $overrides = []): AssistantPendingAction
    {
        $conversation = Conversation::firstOrCreate(['user_id' => $user->id]);

        return AssistantPendingAction::create($overrides + [
            'conversation_id' => $conversation->id,
            'user_id' => $user->id,
            'delivery_id' => $delivery->id,
            'action' => AssistantPendingAction::ACTION_CANCEL_DELIVERY,
            'status' => AssistantPendingAction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function confirm(User $as, AssistantPendingAction $action)
    {
        return $this->actingAsUser($as)->postJson("/api/assistant/actions/{$action->id}/confirm");
    }

    private function statusOf(Delivery $delivery): string
    {
        return $delivery->fresh()->status->name;
    }

    private function botMessages(User $customer)
    {
        return Message::whereHas('conversation', fn($q) => $q->where('user_id', $customer->id))
            ->where('sender_id', $this->bot->id)->orderBy('id')->get();
    }

    // ---- nothing happens without the click ----------------------------------------------

    public function test_the_request_alone_cancels_nothing_and_a_yes_typed_in_the_chat_does_not_either(): void
    {
        config()->set('assistant.enabled', true);
        config()->set('assistant.model', 'fake-model');

        $delivery = $this->deliveryOf($this->alice);

        $llm = new FakeLlmClient([
            FakeLlmClient::callTool('request_cancel_delivery', ['delivery_id' => $delivery->id]),
            FakeLlmClient::text('ignorado'),
            FakeLlmClient::text('Ok, entendi.'),
        ]);
        $this->app->instance(LlmClient::class, $llm);

        $conversation = Conversation::firstOrCreate(['user_id' => $this->alice->id]);

        $ask = $this->actingAsUser($this->alice)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'quero cancelar'])->json('data.id');
        app(AssistantRunner::class)->handle($ask);

        $this->assertSame('pending', $this->statusOf($delivery));

        // "sim" typed in the chat is just a message: the assistant reads it as text.
        $yes = $this->actingAsUser($this->alice)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => 'sim, confirmo'])->json('data.id');
        app(AssistantRunner::class)->handle($yes);

        $this->assertSame('pending', $this->statusOf($delivery), 'only the endpoint cancels');
        $this->assertSame(AssistantPendingAction::STATUS_PENDING, AssistantPendingAction::sole()->status);
    }

    // ---- the click ----------------------------------------------------------------------

    public function test_confirming_cancels_through_the_same_path_as_the_manual_cancellation(): void
    {
        $viaAssistant = $this->deliveryOf($this->alice);
        $byHand = $this->deliveryOf($this->alice);

        $action = $this->pendingFor($this->alice, $viaAssistant);

        $this->confirm($this->alice, $action)->assertOk();
        $this->actingAsUser($this->alice)->postJson("/api/deliveries/{$byHand->id}/cancel")->assertOk();

        foreach ([$viaAssistant, $byHand] as $delivery) {
            $this->assertSame('canceled_by_client', $this->statusOf($delivery));

            $last = $delivery->fresh()->statusHistory()->latest('id')->first();
            $this->assertSame('canceled_by_client', $last->status->name);
            $this->assertSame($this->alice->id, $last->user_id);

            $this->assertTrue(
                Log::where('event_type', 'delivery_status_update')->where('context->delivery_id', $delivery->id)->exists(),
                'the state machine logs it like any other status change'
            );
        }

        $this->assertSame(AssistantPendingAction::STATUS_CONFIRMED, $action->fresh()->status);
        $this->assertNotNull($action->fresh()->resolved_at);
    }

    public function test_the_assistant_closes_the_loop_in_the_chat(): void
    {
        $delivery = $this->deliveryOf($this->alice);

        $this->confirm($this->alice, $this->pendingFor($this->alice, $delivery))->assertOk();

        $this->assertStringContainsString($delivery->tracking_code, $this->botMessages($this->alice)->sole()->body);
        $this->assertStringContainsString('cancelada', $this->botMessages($this->alice)->sole()->body);
    }

    public function test_it_can_still_be_confirmed_while_the_delivery_is_attached(): void
    {
        $delivery = Delivery::factory()->forClient($this->alice, $this->admin())->assignedTo($this->deliveryman())->create();

        $this->confirm($this->alice, $this->pendingFor($this->alice, $delivery))->assertOk();

        $this->assertSame('canceled_by_client', $this->statusOf($delivery));
    }

    // ---- the state machine still decides ------------------------------------------------

    /**
     * @dataProvider pastPickup
     */
    public function test_a_confirmation_fails_when_the_delivery_moved_on_meanwhile(DeliveryStatusEnum $status): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery);

        // Between the question and the click the delivery man picked it up.
        $delivery->update(['delivery_status_id' => \App\Models\DeliveryStatus::where('name', $status->value)->value('id')]);

        $this->confirm($this->alice, $action)->assertStatus(409);

        $this->assertSame($status->value, $this->statusOf($delivery), 'the delivery must be untouched');

        $fresh = $action->fresh();
        $this->assertSame(AssistantPendingAction::STATUS_FAILED, $fresh->status);
        $this->assertNotNull($fresh->error);

        $this->assertStringContainsString('não consegui', mb_strtolower($this->botMessages($this->alice)->sole()->body));
    }

    public function pastPickup(): array
    {
        return [
            'picked up' => [DeliveryStatusEnum::PICKED_UP],
            'in transit' => [DeliveryStatusEnum::IN_TRANSIT],
            'delivered' => [DeliveryStatusEnum::DELIVERED],
        ];
    }

    // ---- who may click, and how many times ----------------------------------------------

    public function test_a_confirmation_of_another_customer_is_not_found_and_cancels_nothing(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery);

        $this->confirm($this->bob, $action)->assertNotFound();
        $this->actingAsUser($this->bob)->postJson("/api/assistant/actions/{$action->id}/reject")->assertNotFound();

        $this->assertSame('pending', $this->statusOf($delivery));
        $this->assertSame(AssistantPendingAction::STATUS_PENDING, $action->fresh()->status);
    }

    public function test_a_confirmation_that_does_not_exist_is_not_found(): void
    {
        $this->actingAsUser($this->alice)->postJson('/api/assistant/actions/999999/confirm')->assertNotFound();
    }

    public function test_the_second_click_does_not_cancel_twice(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery);

        $this->confirm($this->alice, $action)->assertOk();
        $this->confirm($this->alice, $action)->assertStatus(409);

        $canceledSteps = $delivery->fresh()->statusHistory->filter(fn($step) => $step->status->name === 'canceled_by_client');
        $this->assertCount(1, $canceledSteps, 'the delivery is canceled once, not twice');
        $this->assertSame(AssistantPendingAction::STATUS_CONFIRMED, $action->fresh()->status, 'the second click must not rewrite the answer');
    }

    public function test_an_expired_confirmation_is_refused_and_recorded(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery, ['expires_at' => now()->subMinute()]);

        $this->confirm($this->alice, $action)->assertStatus(409);

        $this->assertSame('pending', $this->statusOf($delivery));
        $this->assertSame(AssistantPendingAction::STATUS_EXPIRED, $action->fresh()->status);
    }

    public function test_a_superseded_or_rejected_confirmation_cannot_be_confirmed(): void
    {
        $delivery = $this->deliveryOf($this->alice);

        foreach ([AssistantPendingAction::STATUS_SUPERSEDED, AssistantPendingAction::STATUS_REJECTED] as $status) {
            $action = $this->pendingFor($this->alice, $delivery, ['status' => $status]);

            $this->confirm($this->alice, $action)->assertStatus(409);
        }

        $this->assertSame('pending', $this->statusOf($delivery));
    }

    // ---- rejecting ----------------------------------------------------------------------

    public function test_rejecting_keeps_the_delivery_and_tells_the_customer(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery);

        $this->actingAsUser($this->alice)->postJson("/api/assistant/actions/{$action->id}/reject")->assertOk();

        $this->assertSame('pending', $this->statusOf($delivery));
        $this->assertSame(AssistantPendingAction::STATUS_REJECTED, $action->fresh()->status);
        $this->assertStringContainsString('mantive', $this->botMessages($this->alice)->sole()->body);

        // And it can no longer be confirmed.
        $this->confirm($this->alice, $action)->assertStatus(409);
    }

    // ---- access -------------------------------------------------------------------------

    public function test_only_who_may_cancel_deliveries_reaches_the_endpoints(): void
    {
        $delivery = $this->deliveryOf($this->alice);
        $action = $this->pendingFor($this->alice, $delivery);

        $this->confirm($this->deliveryman(), $action)->assertForbidden();
        $this->confirm($this->support(), $action)->assertForbidden();

        $this->assertSame('pending', $this->statusOf($delivery));
    }

    public function test_a_guest_cannot_reach_the_endpoints(): void
    {
        $action = $this->pendingFor($this->alice, $this->deliveryOf($this->alice));

        $this->postJson("/api/assistant/actions/{$action->id}/confirm")->assertUnauthorized();
    }

    // ---- the atomic claim itself --------------------------------------------------------
    //
    // The service checks the status before claiming, which hides the claim's own condition
    // in sequential tests. Two clicks at the same instant both pass that check; what stops
    // the second is the conditional update, so it is tested on its own.

    public function test_the_claim_is_won_by_one_caller_only(): void
    {
        $repository = app(\App\Contracts\Repositories\AssistantPendingActionInterface::class);
        $action = $this->pendingFor($this->alice, $this->deliveryOf($this->alice));

        $this->assertSame(1, $repository->claimPending($action->id, $this->alice->id));
        $this->assertSame(0, $repository->claimPending($action->id, $this->alice->id));
    }

    public function test_the_claim_refuses_what_is_not_pending_or_not_the_callers_or_expired(): void
    {
        $repository = app(\App\Contracts\Repositories\AssistantPendingActionInterface::class);
        $delivery = $this->deliveryOf($this->alice);

        $rejected = $this->pendingFor($this->alice, $delivery, ['status' => AssistantPendingAction::STATUS_REJECTED]);
        $expired = $this->pendingFor($this->alice, $delivery, ['expires_at' => now()->subSecond()]);
        $alices = $this->pendingFor($this->alice, $delivery);

        $this->assertSame(0, $repository->claimPending($rejected->id, $this->alice->id));
        $this->assertSame(0, $repository->claimPending($expired->id, $this->alice->id));
        $this->assertSame(0, $repository->claimPending($alices->id, $this->bob->id));
    }

    public function test_expiring_never_overwrites_the_answer_of_a_click_that_won(): void
    {
        $repository = app(\App\Contracts\Repositories\AssistantPendingActionInterface::class);
        $action = $this->pendingFor($this->alice, $this->deliveryOf($this->alice), ['expires_at' => now()->addMinute()]);

        $repository->claimPending($action->id, $this->alice->id);

        // Even past its expiry, a confirmed action stays confirmed.
        $action->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(0, $repository->expireIfDue($action->id));
        $this->assertSame(AssistantPendingAction::STATUS_CONFIRMED, $action->fresh()->status);
    }
}
