<?php

namespace App\Contracts\Repositories;

use App\Models\Message;

interface MessageInterface
{
    public function create(array $data): Message;
    public function findById(int $id): ?Message;

    public function markInboundAsRead(int $conversationId): int;
    public function markOutboundAsRead(int $conversationId): int;

    public function countUnreadInbound(): int;
    public function countUnreadOutboundFor(int $userId): int;
}
