<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;
use App\Contracts\Repositories\DeliveryInterface;
use App\Enums\DeliveryStatusEnum;
use App\Services\DeliveryTransitionValidator;

class CanCancelDeliveryTool extends ValidatedTool
{
    use FindsClientDelivery;

    public function __construct(
        private DeliveryInterface $deliveries,
        private DeliveryTransitionValidator $validator,
    ) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'can_cancel_delivery',
            description: 'Tells whether the customer can still cancel a delivery, and why not when they cannot. '
                . 'It does not cancel anything.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'delivery_id' => ['type' => 'integer', 'description' => 'The id of the delivery.'],
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

        // The same rule the manual cancellation goes through, asked without doing it.
        $reason = $this->validator->checkTransition($delivery, DeliveryStatusEnum::CANCELED_BY_CLIENT, $context->user);

        return [
            'found' => true,
            'tracking_code' => $delivery->tracking_code,
            'status' => $delivery->status->name,
            'can_cancel' => $reason === null,
            'reason' => $reason,
        ];
    }
}
