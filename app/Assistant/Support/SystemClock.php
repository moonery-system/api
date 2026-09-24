<?php

namespace App\Assistant\Support;

class SystemClock implements Clock, Sleeper
{
    public function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    public function sleepMs(int $milliseconds): void
    {
        if ($milliseconds > 0) usleep($milliseconds * 1000);
    }
}
