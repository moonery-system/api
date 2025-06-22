<?php

namespace App\Notifications\Strategies;

class DeliveryCreatedClientDescription implements NotificationDescriptionStrategy
{
    public function getDescription(array $context): string
    {
        $items = $context['items'] ?? [];
        $itemNames = array_map(fn($item) => $item['name'], $items);

        return 'Delivery Items: ' . implode(', ', $itemNames) . '.';
    }
}