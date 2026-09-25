<?php

namespace App\Repositories;

use App\Contracts\Repositories\MessageInterface;
use App\Models\Message;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read state is decided by the direction of the message, not by who is reading.
 *
 * A conversation has exactly two sides, and any support agent may answer, so "who
 * read it" is not a useful key: inbound (written by the person being attended) is
 * waiting on support, outbound is waiting on that person.
 */
class MessageRepository implements MessageInterface
{
    public function create(array $data): Message
    {
        return Message::create($data);
    }

    public function findById(int $id): ?Message
    {
        return Message::with(['sender', 'conversation'])->find($id);
    }

    /**
     * @return Collection<int, Message>
     */
    public function recentForConversation(int $conversationId, int $limit): Collection
    {
        return Message::where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();
    }

    public function markInboundAsRead(int $conversationId): int
    {
        return $this->inbound()
            ->where('messages.conversation_id', $conversationId)
            ->whereNull('messages.read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    public function markOutboundAsRead(int $conversationId): int
    {
        return $this->outbound()
            ->where('messages.conversation_id', $conversationId)
            ->whereNull('messages.read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    public function countUnreadInbound(): int
    {
        return $this->inbound()->whereNull('messages.read_at')->count();
    }

    public function countUnreadOutboundFor(int $userId): int
    {
        return $this->outbound()
            ->where('conversations.user_id', $userId)
            ->whereNull('messages.read_at')
            ->count();
    }

    private function inbound()
    {
        return $this->joined()->whereColumn('messages.sender_id', 'conversations.user_id');
    }

    private function outbound()
    {
        return $this->joined()->whereColumn('messages.sender_id', '!=', 'conversations.user_id');
    }

    private function joined()
    {
        return DB::table('messages')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id');
    }
}
