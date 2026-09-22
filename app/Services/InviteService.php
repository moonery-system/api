<?php

namespace App\Services;

use App\Contracts\Repositories\InviteInterface;
use App\Contracts\Repositories\UserInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InviteService
{
    public const INVITE_ROUTING_KEY = 'invites.email';

    public const PASSWORD_RESET_ROUTING_KEY = 'password-resets.email';

    public function __construct(
        private UserInterface $userRepository,
        private InviteInterface $inviteRepository,

        private RabbitMQPublisher $publisher
    ) {}

    public function createForUserId($userId, string $routingKey = self::INVITE_ROUTING_KEY)
    {
        $this->inviteRepository->expireActiveInvite($userId);

        $invite = $this->inviteRepository->create([
            'user_id' => $userId,
            'token' => Str::random(60),
            'expires_at' => Carbon::now()->addHour(),
        ]);

        try {
            $this->publisher->publish($routingKey, [
                'invite_id' => $invite->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to publish the invite email', [
                'invite_id' => $invite->id,
                'message' => $e->getMessage(),
            ]);
        }

        return $invite;
    }

    /**
     * Same token machinery either way -- only the wording changes. An active user
     * asking for a link is resetting a password, not being invited.
     */
    public function createForEmail($email)
    {
        $user = $this->userRepository->findByEmail(email: $email);
        if (!$user) return null;

        return $this->createForUserId(
            userId: $user->id,
            routingKey: $user->activated_at ? self::PASSWORD_RESET_ROUTING_KEY : self::INVITE_ROUTING_KEY
        );
    }

    public function validateToken($token)
    {
        if (!$token) return false;

        $invite = $this->inviteRepository->findByToken(token: $token);

        if (!$invite || $invite->expires_at->isPast() || $invite->used_at) return false;

        return $invite;
    }
}
