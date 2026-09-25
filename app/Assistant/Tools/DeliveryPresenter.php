<?php

namespace App\Assistant\Tools;

use App\Models\Delivery;

/**
 * What the assistant is allowed to know about a delivery, and so what may reach the
 * provider. The rule is "only what answers the customer's question": no delivery man
 * (name, e-mail, id), no e-mail or phone of the customer, no zip code or complement,
 * and no name of whoever moved the delivery along.
 */
class DeliveryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Delivery $delivery): array
    {
        return [
            'delivery_id' => $delivery->id,
            'tracking_code' => $delivery->tracking_code,
            'status' => $delivery->status->name,
            'status_description' => $delivery->status->label,
            'delivered_at' => $delivery->delivered_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Delivery $delivery): array
    {
        return $this->summary($delivery) + [
            'scheduled_to' => $delivery->scheduled_to,
            'items' => $delivery->items->take(20)->map(fn($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'weight_kg' => $item->weight,
            ])->values()->all(),
            'address' => $delivery->address ? [
                'street' => $delivery->address->address_line,
                'neighborhood' => $delivery->address->neighborhood,
                'city' => $delivery->address->city,
                'state' => $delivery->address->state,
            ] : null,
            'history' => $delivery->statusHistory->take(-20)->map(fn($step) => [
                'status' => $step->status->name,
                'at' => $step->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
