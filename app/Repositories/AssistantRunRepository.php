<?php

namespace App\Repositories;

use App\Contracts\Repositories\AssistantRunInterface;
use App\Models\AssistantRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssistantRunRepository implements AssistantRunInterface
{
    public function claim(int $messageId, int $conversationId, int $userId, string $provider, ?string $model, int $staleAfterSeconds): ?AssistantRun
    {
        $now = Carbon::now();

        $inserted = DB::table('assistant_runs')->insertOrIgnore([
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'status' => AssistantRun::STATUS_RUNNING,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 0) {
            // A row exists. Only a run stuck in 'running' for longer than the whole
            // deadline is taken again: it means the consumer died in the middle of it.
            $retaken = AssistantRun::where('message_id', $messageId)
                ->where('status', AssistantRun::STATUS_RUNNING)
                ->where('updated_at', '<', $now->copy()->subSeconds($staleAfterSeconds))
                ->update(['updated_at' => $now]);

            if ($retaken === 0) return null;
        }

        return AssistantRun::where('message_id', $messageId)->first();
    }

    public function finish(AssistantRun $run, array $data): AssistantRun
    {
        $run->update($data);

        return $run;
    }

    public function countForUserSince(int $userId, Carbon $since, int $exceptMessageId): int
    {
        return AssistantRun::where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->where('message_id', '!=', $exceptMessageId)
            ->count();
    }
}
