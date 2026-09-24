<?php

namespace Tests\Support;

use App\Assistant\Support\Clock;
use App\Assistant\Support\Sleeper;

/**
 * Time that only moves when somebody sleeps: no test waits for real.
 */
class FakeClock implements Clock, Sleeper
{
    /** @var array<int, int> */
    public array $sleeps = [];

    public function __construct(private int $nowMs = 1_800_000_000_000)
    {
    }

    public function nowMs(): int
    {
        return $this->nowMs;
    }

    public function sleepMs(int $milliseconds): void
    {
        $this->sleeps[] = $milliseconds;
        $this->nowMs += $milliseconds;
    }

    public function advance(int $milliseconds): void
    {
        $this->nowMs += $milliseconds;
    }
}
