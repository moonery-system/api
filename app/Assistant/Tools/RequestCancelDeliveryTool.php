<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;
use App\Contracts\Repositories\AssistantPendingActionInterface;
use App\Contracts\Repositories\DeliveryInterface;
use App\Enums\DeliveryStatusEnum;
use App\Models\AssistantPendingAction;
use App\Services\DeliveryTransitionValidator;
use Carbon\Carbon;

/**
 * Does NOT cancel. It leaves a confirmation waiting, which the customer resolves with a
 * click on a separate endpoint; only that one runs the cancellation. Nothing the model
 * says, and nothing typed in the chat, can stand in for that click.
 */
class RequestCancelDeliveryTool extends ValidatedTool
{
    use FindsClientDelivery;

    public function __construct(
        private DeliveryInterface $deliveries,
        private AssistantPendingActionInterface $pendingActions,
        private DeliveryTransitionValidator $validator,
    ) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'request_cancel_delivery',
            description: 'Asks the customer to confirm the cancellation of a delivery. It does NOT cancel it: '
                . 'the customer has to confirm with a button that appears in the chat. '
                . 'Call it only after the customer said they want to cancel, and never say a delivery was '
                . 'canceled after calling it.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'delivery_id' => ['type' => 'integer', 'description' => 'The id of the delivery to cancel.'],
                ],
                'required' => ['delivery_id'],
            ],
        );
    }

    protected function rules(): array
    {
        return ['delivery_id' => ['required', 'integer']];
    }

    protected function handle(ToolContext $context, array $arguments): array
    {
        $delivery = $this->findOwnDelivery($this->deliveries, $context, (int) $arguments['delivery_id']);

        if (!$delivery) return $this->notFound();

        $reason = $this->validator->checkTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $context->user);

        if ($reason !== null) {
            return [
                'found' => true,
                'tracking_code' => $delivery->tracking_code,
                'awaiting_confirmation' => false,
                'can_cancel' => false,
                'reason' => $reason,
            ];
        }

        // Only the latest question can be answered.
        $this->pendingActions->supersedePending($context->user->id, $delivery->id);

        $ttl = (int) config('assistant.confirmation_ttl_minutes');

        $action = $this->pendingActions->create([
            'conversation_id' => $context->conversation->id,
            'user_id' => $context->user->id,
            'delivery_id' => $delivery->id,
            'action' => AssistantPendingAction::ACTION_CANCEL_DELIVERY,
            'status' => AssistantPendingAction::STATUS_PENDING,
            'expires_at' => Carbon::now()->addMinutes($ttl),
        ]);

        $context->pendingActionId = $action->id;
        $context->pendingDeliveryId = $delivery->id;
        $context->pendingTrackingCode = $delivery->tracking_code;

        return [
            'found' => true,
            'tracking_code' => $delivery->tracking_code,
            'awaiting_confirmation' => true,
            'expires_in_minutes' => $ttl,
            'note' => 'Nothing was canceled. The customer must confirm using the button in the chat.',
        ];
    }
}
