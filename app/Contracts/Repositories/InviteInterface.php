<?php

namespace App\Contracts\Repositories;

use App\Models\Invite;

interface InviteInterface
{
    public function create(array $data): Invite;
    public function findById(int $id): ?Invite;
    public function findByToken(string $token): ?Invite;
    public function findByUserId(int $userId): ?Invite;
    public function expireActiveInvite(int $userId): void;

    /**
     * Marks the e-mail of an invite (or password reset -- same row, same column) as sent,
     * so a retried delivery does not send it twice.
     */
    public function markEmailSent(int $inviteId): void;
}
