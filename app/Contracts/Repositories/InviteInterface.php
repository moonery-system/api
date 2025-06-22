<?php

namespace App\Contracts\Repositories;

use App\Models\Invite;

interface InviteInterface
{
    public function create(array $data): Invite;
    public function findByToken(string $token): ?Invite;
    public function findByUserId(int $userId): ?Invite;
    public function expireActiveInvite(int $userId): void;
}
