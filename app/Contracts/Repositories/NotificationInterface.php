<?php

namespace App\Contracts\Repositories;

use App\Models\Notification;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationInterface
{
    public function createWithUsers(string $title, string $description, array $userIds): Notification;
    public function findById(int $id): ?Notification;

    public function findByUserPaginated(int $userId, int $perPage = 10): LengthAwarePaginator;
    public function countUnreadByUser(int $userId): int;
    public function markAsRead(int $userId, int $notificationId): bool;
}
