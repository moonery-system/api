<?php

namespace App\Assistant\Tools;

use App\Contracts\Repositories\DeliveryInterface;
use App\Models\Delivery;

/**
 * The single way a tool reaches a delivery: through the client of the conversation.
 * A delivery of another customer and a delivery that does not exist are the same thing
 * from here -- null -- so the answer cannot confirm that the other one exists.
 */
trait FindsClientDelivery
{
    private function findOwnDelivery(DeliveryInterface $deliveries, ToolContext $context, ?int $id, ?string $trackingCode = null): ?Delivery
    {
        if ($id !== null) return $deliveries->findByIdForClient($id, $context->user->id);

        if ($trackingCode !== null && $trackingCode !== '') {
            return $deliveries->findByTrackingCodeForClient(strtoupper(trim($trackingCode)), $context->user->id);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function notFound(): array
    {
        return [
            'found' => false,
            'message' => 'No delivery with that reference was found for this customer.',
        ];
    }
}
