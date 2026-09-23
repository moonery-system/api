<?php

namespace App\Repositories;

use App\Contracts\Repositories\ConversationInterface;
use App\Models\Conversation;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository implements ConversationInterface
{
    public function findById(int $id): ?Conversation
    {
        return Conversation::with(['user', 'messages.sender'])->find($id);
    }

    public function findByUserId(int $userId): ?Conversation
    {
        return Conversation::with(['user', 'messages.sender'])
            ->where('user_id', $userId)
            ->first();
    }

    public function createForUser(int $userId): Conversation
    {
        return Conversation::create(['user_id' => $userId]);
    }

    public function findAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return Conversation::with('user')
            ->withCount(['messages as unread_count' => function ($query) {
                // Inbound and still unanswered: written by the person being attended.
                $query->whereNull('read_at')
                    ->whereColumn('messages.sender_id', 'conversations.user_id');
            }])
            ->latest('updated_at')
            ->paginate($perPage);
    }
}
