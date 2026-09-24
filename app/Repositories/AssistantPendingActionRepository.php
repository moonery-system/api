<?php

namespace App\Repositories;

use App\Contracts\Repositories\AssistantPendingActionInterface;
use App\Models\AssistantPendingAction;
use Carbon\Carbon;

class AssistantPendingActionRepository implements AssistantPendingActionInterface
{
    public function create(array $data): AssistantPendingAction
    {
        return AssistantPendingAction::create($data);
    }

    public function findByIdForUser(int $id, int $userId): ?AssistantPendingAction
    {
        return AssistantPendingAction::where('user_id', $userId)->find($id);
    }

    public function attachMessage(int $id, int $messageId): int
    {
        return AssistantPendingAction::where('id', $id)->update(['message_id' => $messageId]);
    }

    public function supersedePending(int $userId, int $deliveryId): int
    {
        return AssistantPendingAction::where('user_id', $userId)
            ->where('delivery_id', $deliveryId)
            ->where('status', AssistantPendingAction::STATUS_PENDING)
            ->update([
                'status' => AssistantPendingAction::STATUS_SUPERSEDED,
                'resolved_at' => Carbon::now(),
            ]);
    }

    public function claimPending(int $id, int $userId): int
    {
        $now = Carbon::now();

        return AssistantPendingAction::where('id', $id)
            ->where('user_id', $userId)
            ->where('status', AssistantPendingAction::STATUS_PENDING)
            ->where('expires_at', '>', $now)
            ->update([
                'status' => AssistantPendingAction::STATUS_CONFIRMED,
                'resolved_at' => $now,
            ]);
    }

    public function resolve(int $id, string $status, ?string $error = null): int
    {
        return AssistantPendingAction::where('id', $id)->update([
            'status' => $status,
            'resolved_at' => Carbon::now(),
            'error' => $error,
        ]);
    }
}
