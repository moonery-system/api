<?php

namespace App\Services;

use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\UserInterface;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InviteService
{
    public function __construct(
        private UserInterface $userRepository,
        private InviteInterface $inviteRepository
    ) {}

    public function createForUserId($userId)
    {
        $this->inviteRepository->expireActiveInvite($userId);

        $invite = $this->inviteRepository->create([
            'user_id' => $userId,
            'token' => Str::random(60),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        return $invite;
    }

    public function createForEmail($email)
    {
        $user = $this->userRepository->findByEmail(email: $email);
        if (!$user) return null;

        return $this->createForUserId(userId: $user->id);
    }

    public function validateToken($token)
    {
        if (!$token) return false;

        $invite = $this->inviteRepository->findByToken(token: $token);

        if (!$invite || $invite->expires_at->isPast() || $invite->used_at) return false;

        return $invite;
    }
}
