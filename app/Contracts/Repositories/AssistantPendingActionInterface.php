<?php

namespace App\Contracts\Repositories;

use App\Models\AssistantPendingAction;

interface AssistantPendingActionInterface
{
    public function create(array $data): AssistantPendingAction;

    public function findByIdForUser(int $id, int $userId): ?AssistantPendingAction;

    public function attachMessage(int $id, int $messageId): int;

    /**
     * Drops the confirmations still waiting for the same delivery, so that only the
     * latest question can be answered.
     */
    public function supersedePending(int $userId, int $deliveryId): int;

    /**
     * pending -> confirmed, only when it is still pending and not expired. Being a
     * conditional update, two clicks cannot both win. Returns the affected rows.
     */
    public function claimPending(int $id, int $userId): int;

    /**
     * pending -> expired, only when it is still pending AND past its expiry. A no-op for
     * anything else, so it can never overwrite the answer of a click that won the race.
     */
    public function expireIfDue(int $id): int;

    public function resolve(int $id, string $status, ?string $error = null): int;
}
