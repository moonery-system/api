<?php

namespace App\Factories;

use App\Enums\NotificationTitleEnum;
use App\Notifications\Strategies\{
    DeliveryCreatedClientDescription,
    DeliveryCreatedDeliverymanDescription,
    DeliveryStatusUpdateDescription,
    NotificationDescriptionStrategy
};

class NotificationDescriptionFactory
{
    public static function make(NotificationTitleEnum $title): NotificationDescriptionStrategy
    {
        return match ($title) {
            NotificationTitleEnum::DELIVERY_CREATED_CLIENT => new DeliveryCreatedClientDescription(),
            NotificationTitleEnum::DELIVERY_CREATED_DELIVERY_MAN => new DeliveryCreatedDeliverymanDescription(),
            NotificationTitleEnum::DELIVERY_STATUS_UPDATE_CLIENT => new DeliveryStatusUpdateDescription(),
            default => throw new \InvalidArgumentException('no description strategy'),
        };
    }
}
