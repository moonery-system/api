<?php

namespace App\Assistant\Tools;

use App\Assistant\Llm\ToolDefinition;
use App\Contracts\Repositories\DeliveryInterface;
use App\Enums\DeliveryStatusEnum;
use Illuminate\Validation\Rule;

class ListMyDeliveriesTool extends ValidatedTool
{
    private const MAX_LIMIT = 10;

    public function __construct(
        private DeliveryInterface $deliveries,
        private DeliveryPresenter $presenter,
    ) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'list_my_deliveries',
            description: 'Lists the deliveries of the customer you are talking to, newest first. '
                . 'Use it to find out which deliveries the customer has, or to find one by its status.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'status' => [
                        'type' => 'string',
                        'description' => 'Only deliveries in this status.',
                        'enum' => DeliveryStatusEnum::values(),
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'How many deliveries to return, at most ' . self::MAX_LIMIT . '. Default 5.',
                    ],
                ],
            ],
        );
    }

    protected function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(DeliveryStatusEnum::values())],
            'limit' => ['nullable', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ];
    }

    protected function handle(ToolContext $context, array $arguments): array
    {
        $statusId = null;

        if (!empty($arguments['status'])) {
            $statusId = $this->deliveries->findDeliveryStatusByName($arguments['status'])?->id;
        }

        $found = $this->deliveries->findByClientLimited(
            clientId: $context->user->id,
            statusId: $statusId,
            limit: (int) ($arguments['limit'] ?? 5),
        );

        return [
            'count' => $found->count(),
            'deliveries' => $found->map(fn($delivery) => $this->presenter->summary($delivery))->values()->all(),
        ];
    }
}
