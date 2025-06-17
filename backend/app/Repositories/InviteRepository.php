<?php

namespace App\Repositories;

use App\Models\Invite;


class InviteRepository
{
    public function findByToken(string $token): ?Invite
    {
        return Invite::where('token', $token)->first();
    }

    public function findByUserId(int $userId): ?Invite
    {
        return Invite::where('user_id', $userId)->first();
    }
}
