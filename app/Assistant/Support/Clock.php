<?php

namespace App\Assistant\Support;

interface Clock
{
    /**
     * Milliseconds since the epoch.
     */
    public function nowMs(): int;
}
