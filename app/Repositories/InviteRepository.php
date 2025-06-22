<?php

namespace App\Repositories;

use App\Contracts\Repositories\InviteInterface;
use App\Models\Invite;
use Carbon\Carbon;

class InviteRepository implements InviteInterface
{
    public function create(array $data): Invite
    {
        return Invite::create($data);
    }

    public function findByToken(string $token): ?Invite
    {
        return Invite::where('token', $token)->first();
    }

    public function findByUserId(int $userId): ?Invite
    {
        return Invite::where('user_id', $userId)->first();
    }

    public function expireActiveInvite(int $userId): void
    {
        Invite::where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->update(['expires_at' => Carbon::now()]);
    }
}
