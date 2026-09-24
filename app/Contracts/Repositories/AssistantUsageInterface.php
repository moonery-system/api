<?php

namespace App\Contracts\Repositories;

interface AssistantUsageInterface
{
    /**
     * Counts one provider call on the given day, atomically.
     */
    public function incrementCalls(string $provider, string $date): void;

    public function addTokens(string $provider, string $date, int $input, int $output): void;

    public function callsOn(string $provider, string $date): int;

    /**
     * Input plus output tokens of the day, across providers.
     */
    public function tokensOn(string $date): int;
}
