<?php

namespace App\Notifications\Strategies;

class DeliveryStatusUpdateDescription implements NotificationDescriptionStrategy
{
    public function getDescription(array $context): string
    {
        $status = $context['status'];
        return $status;
    }
}
