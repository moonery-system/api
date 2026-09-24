<?php

namespace App\Repositories;

use App\Contracts\Repositories\AssistantUsageInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssistantUsageRepository implements AssistantUsageInterface
{
    public function incrementCalls(string $provider, string $date): void
    {
        $this->upsert($provider, $date, calls: 1, input: 0, output: 0);
    }

    public function addTokens(string $provider, string $date, int $input, int $output): void
    {
        $this->upsert($provider, $date, calls: 0, input: $input, output: $output);
    }

    public function callsOn(string $provider, string $date): int
    {
        return (int) DB::table('assistant_usage_daily')
            ->where('date', $date)
            ->where('provider', $provider)
            ->value('calls');
    }

    public function tokensOn(string $date): int
    {
        return (int) DB::table('assistant_usage_daily')
            ->where('date', $date)
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) AS total')
            ->value('total');
    }

    /**
     * The increment happens inside the database, so two writers cannot lose an update.
     * Postgres only, like the rest of the project.
     */
    private function upsert(string $provider, string $date, int $calls, int $input, int $output): void
    {
        $now = Carbon::now();

        DB::statement(
            'INSERT INTO assistant_usage_daily (date, provider, calls, input_tokens, output_tokens, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT (date, provider) DO UPDATE SET
                calls = assistant_usage_daily.calls + EXCLUDED.calls,
                input_tokens = assistant_usage_daily.input_tokens + EXCLUDED.input_tokens,
                output_tokens = assistant_usage_daily.output_tokens + EXCLUDED.output_tokens,
                updated_at = EXCLUDED.updated_at',
            [$date, $provider, $calls, $input, $output, $now, $now]
        );
    }
}
