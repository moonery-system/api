<?php

namespace App\Contracts\Repositories;

use App\Models\Conversation;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConversationInterface
{
    public function findById(int $id): ?Conversation;
    public function findByUserId(int $userId): ?Conversation;
    public function createForUser(int $userId): Conversation;
    /**
     * active -> handed_off, only if still active. Returns the affected rows, so the
     * caller knows whether it was the one that flipped it.
     */
    public function markHandedOff(int $id, string $reason): int;
    public function findAllPaginated(int $perPage = 10): LengthAwarePaginator;
}
