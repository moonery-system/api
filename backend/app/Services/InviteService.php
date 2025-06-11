<?php

namespace App\Services;

use App\Models\Invite;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InviteService
{
    public function createForUser($userId)
    {
        return Invite::create([
            'user_id' => $userId,
            'token' => Str::random(60),
            'expires_at' => Carbon::now()->addHour(),
        ]);
    }
}