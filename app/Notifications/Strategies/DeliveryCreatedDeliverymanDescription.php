<?php

namespace App\Notifications\Strategies;

class DeliveryCreatedDeliverymanDescription implements NotificationDescriptionStrategy
{
    public function getDescription(array $context): string
    {
        $items = $context['items'] ?? [];
        $itemNames = array_map(fn($item) => $item['name'], $items);

        return 'Delivery items: ' . implode(', ', $itemNames) . '.';
    }
}
