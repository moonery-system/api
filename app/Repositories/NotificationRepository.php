<?php

namespace App\Repositories;

use App\Contracts\Repositories\NotificationInterface;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationRepository implements NotificationInterface
{
    public function createWithUsers(string $title, string $description, array $userIds): Notification
    {
        return DB::transaction(function () use ($title, $description, $userIds) {
            $notification = Notification::create([
                'title'       => $title,
                'description' => $description,
            ]);

            $notification->users()->attach($userIds);

            return $notification;
        });
    }

    public function findById(int $id): ?Notification
    {
        return Notification::with('users')->find($id);
    }

    /**
     * Joins the pivot so each row already carries this user's read_at.
     */
    public function findByUserPaginated(int $userId, int $perPage = 10): LengthAwarePaginator
    {
        return Notification::query()
            ->join('user_notifications', 'user_notifications.notification_id', '=', 'notifications.id')
            ->where('user_notifications.user_id', $userId)
            ->select('notifications.*', 'user_notifications.read_at')
            ->orderByDesc('notifications.created_at')
            ->paginate($perPage);
    }

    public function countUnreadByUser(int $userId): int
    {
        return DB::table('user_notifications')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();
    }

    public function markAsRead(int $userId, int $notificationId): bool
    {
        $pivot = DB::table('user_notifications')
            ->where('user_id', $userId)
            ->where('notification_id', $notificationId);

        if (!$pivot->exists()) return false;

        // Idempotent: marking an already read one again is not an error.
        $pivot->whereNull('read_at')->update(['read_at' => Carbon::now()]);

        return true;
    }

    /**
     * @return Collection<int, User>
     */
    public function usersPendingEmail(int $notificationId): Collection
    {
        return User::query()
            ->join('user_notifications', 'user_notifications.user_id', '=', 'users.id')
            ->where('user_notifications.notification_id', $notificationId)
            ->whereNull('user_notifications.emailed_at')
            ->select('users.*')
            ->get();
    }

    public function markEmailed(int $notificationId, int $userId): void
    {
        DB::table('user_notifications')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->update(['emailed_at' => Carbon::now()]);
    }
}
