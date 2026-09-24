<?php

namespace App\Repositories;

use App\Contracts\Repositories\ConversationInterface;
use App\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationRepository implements ConversationInterface
{
    public function findById(int $id): ?Conversation
    {
        return Conversation::with(['user', 'messages.sender', 'messages.pendingAction'])->find($id);
    }

    public function findByUserId(int $userId): ?Conversation
    {
        return Conversation::with(['user', 'messages.sender', 'messages.pendingAction'])
            ->where('user_id', $userId)
            ->first();
    }

    public function createForUser(int $userId): Conversation
    {
        return Conversation::create(['user_id' => $userId]);
    }

    public function markHandedOff(int $id, string $reason): int
    {
        return Conversation::where('id', $id)
            ->where('assistant_status', Conversation::ASSISTANT_ACTIVE)
            ->update([
                'assistant_status' => Conversation::ASSISTANT_HANDED_OFF,
                'handed_off_at' => Carbon::now(),
                'handoff_reason' => $reason,
            ]);
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
