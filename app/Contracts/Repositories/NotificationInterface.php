<?php

namespace App\Contracts\Repositories;

use App\Models\Notification;

interface NotificationInterface
{
    public function createWithUsers(string $title, string $description, array $userIds): Notification;
    public function findById(int $id): ?Notification;
}
