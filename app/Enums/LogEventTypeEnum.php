<?php

namespace App\Enums;

enum LogEventTypeEnum: string
{
    case USER_CREATED = 'user_created';
    case USER_UPDATED = 'user_updated';
    case USER_DELETED = 'user_deleted';
    case USER_ACTIVATED = 'user_activated';
    
    case CLIENT_CREATED = 'client_created';
    case CLIENT_UPDATED = 'client_updated';
    case CLIENT_DELETED = 'client_deleted';
    
    case CLIENT_ADDRESS_CREATED = 'client_address_created';
    case CLIENT_ADDRESS_DELETED = 'client_address_deleted';

    case DELIVERY_CREATED = 'delivery_created';
    case DELIVERY_STATUS_UPDATE = 'delivery_status_update';
    case DELIVERY_DELETED = 'delivery_deleted';

    case DELIVERY_ITEMS_CREATED = 'delivery_items_created';
}