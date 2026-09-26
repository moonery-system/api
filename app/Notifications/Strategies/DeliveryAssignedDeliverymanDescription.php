<?php

namespace App\Notifications\Strategies;

class DeliveryAssignedDeliverymanDescription implements NotificationDescriptionStrategy
{
    public function getDescription(array $context): string
    {
        $trackingCode = $context['tracking_code'] ?? null;

        return $trackingCode
            ? "Delivery {$trackingCode} is now yours to pick up."
            : 'A delivery is now yours to pick up.';
    }
}
