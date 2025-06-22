<?php

namespace App\Enums;

enum NotificationTitleEnum: string
{
    case DELIVERY_CREATED_CLIENT = 'You delivery has been created!';
    case DELIVERY_STATUS_UPDATE_CLIENT = 'The status of your delivery has been updated!';

    case DELIVERY_CREATED_DELIVERY_MAN = 'A new delivery has been created!';
}