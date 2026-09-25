<?php

namespace App\Contracts\Repositories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Collection;

interface MessageInterface
{
    public function create(array $data): Message;
    public function findById(int $id): ?Message;

    /**
     * The latest messages of a conversation, oldest first.
     */
    /**
     * @return Collection<int, Message>
     */
    public function recentForConversation(int $conversationId, int $limit): Collection;

    public function markInboundAsRead(int $conversationId): int;
    public function markOutboundAsRead(int $conversationId): int;

    public function countUnreadInbound(): int;
    public function countUnreadOutboundFor(int $userId): int;
}
