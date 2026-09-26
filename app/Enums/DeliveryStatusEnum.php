<?php

namespace App\Enums;

enum DeliveryStatusEnum: string
{
    case PENDING = 'pending';
    case ATTACHED = 'attached';
    case PICKED_UP = 'picked_up';
    case IN_TRANSIT = 'in_transit';
    case DELIVERED = 'delivered';
    case CLIENT_ADDRESS_NOT_FOUND = 'client_address_not_found';
    case CLIENT_NOT_FOUND = 'client_not_found';
    case CANCELED_BY_CLIENT = 'canceled_by_client';
    case CANCELED_BY_ADMIN = 'canceled_by_admin';
    case RETURN_TO_SENDER = 'return_to_sender';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
