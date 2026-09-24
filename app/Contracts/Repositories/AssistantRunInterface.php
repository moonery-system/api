<?php

namespace App\Contracts\Repositories;

use App\Models\AssistantRun;
use Carbon\Carbon;

interface AssistantRunInterface
{
    /**
     * Takes the message for processing. Null when it is already taken (or done): that
     * is what makes the assistant answer a message at most once.
     */
    public function claim(int $messageId, int $conversationId, int $userId, string $provider, ?string $model, int $staleAfterSeconds): ?AssistantRun;

    public function finish(AssistantRun $run, array $data): AssistantRun;

    /**
     * Runs of a user since a moment, not counting the message being processed.
     */
    public function countForUserSince(int $userId, Carbon $since, int $exceptMessageId): int;
}
