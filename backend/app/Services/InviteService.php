<?php

namespace App\Services;

use App\Models\Invite;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InviteService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    public function createForUserId($userId)
    {
        Invite::where('user_id', $userId)
            ->whereNull('used_at')
            ->where('expires_at', '>', Carbon::now())
            ->update(['expires_at' => Carbon::now()]);

        return Invite::create([
            'user_id' => $userId,
            'token' => Str::random(60),
            'expires_at' => Carbon::now()->addHour(),
        ]);
    }

    public function createForEmail($email)
    {
        $user = $this->userRepository->findByEmail(email: $email);
        if (!$user) return null;

        return $this->createForUserId(userId: $user->id);
    }

    public function getInviteByToken($token)
    {
        return Invite::where('token', $token)->first();
    }

    public function getInviteByUserId($userId)
    {
        return Invite::where('user_id', $userId);
    }

    public function validateToken($token)
    {
        if (!$token) return false;

        $invite = $this->getInviteByToken(token: $token);

        if (!$invite || $invite->expires_at->isPast() || $invite->used_at) return false;

        return $invite;
    }
}
