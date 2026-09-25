<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;
use App\Contracts\Repositories\DeliveryInterface;

class GetDeliveryTool extends ValidatedTool
{
    use FindsClientDelivery;

    public function __construct(
        private DeliveryInterface $deliveries,
        private DeliveryPresenter $presenter,
    ) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'get_delivery',
            description: 'Gets one delivery of the customer: status, items, delivery address, '
                . 'scheduled date, delivery date and the history of status changes. '
                . 'Give the delivery_id or the tracking_code (like MNY-2026-ABC123).',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'delivery_id' => ['type' => 'integer', 'description' => 'The id of the delivery.'],
                    'tracking_code' => ['type' => 'string', 'description' => 'The tracking code of the delivery.'],
                ],
            ],
        );
    }

    protected function rules(): array
    {
        return [
            'delivery_id' => ['nullable', 'integer', 'required_without:tracking_code'],
            'tracking_code' => ['nullable', 'string', 'max:40', 'required_without:delivery_id'],
        ];
    }

    protected function handle(ToolContext $context, array $arguments): array
    {
        $delivery = $this->findOwnDelivery(
            $this->deliveries,
            $context,
            isset($arguments['delivery_id']) ? (int) $arguments['delivery_id'] : null,
            $arguments['tracking_code'] ?? null,
        );

        if (!$delivery) return $this->notFound();

        return ['found' => true] + $this->presenter->detail($delivery);
    }
}
