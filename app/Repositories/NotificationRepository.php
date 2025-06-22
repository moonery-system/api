<?php

namespace App\Repositories;

use App\Contracts\Repositories\NotificationInterface;
use App\Models\Notification;
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

    // TODO
    //  find notifications from users
    // public function findByUserId(int $userId): ?Notification
    // {
    //     return Not::where('user_id', $userId)->first();
    // }
}
