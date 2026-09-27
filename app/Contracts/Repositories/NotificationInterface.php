<?php

namespace App\Contracts\Repositories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface NotificationInterface
{
    public function createWithUsers(string $title, string $description, array $userIds): Notification;
    public function findById(int $id): ?Notification;

    public function findByUserPaginated(int $userId, int $perPage = 10): LengthAwarePaginator;
    public function countUnreadByUser(int $userId): int;
    public function markAsRead(int $userId, int $notificationId): bool;

    /**
     * Recipients of a notification who have not been e-mailed yet: what a retried
     * delivery should send to, so the ones already reached are not mailed twice.
     *
     * @return Collection<int, User>
     */
    public function usersPendingEmail(int $notificationId): Collection;

    public function markEmailed(int $notificationId, int $userId): void;
}
