<?php

namespace App\Contracts\Repositories;

use App\Models\Conversation;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConversationInterface
{
    public function findById(int $id): ?Conversation;
    public function findByUserId(int $userId): ?Conversation;
    public function createForUser(int $userId): Conversation;
    public function findAllPaginated(int $perPage = 10): LengthAwarePaginator;
}
