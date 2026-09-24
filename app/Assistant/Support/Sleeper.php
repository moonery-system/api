<?php

namespace App\Assistant\Support;

interface Sleeper
{
    public function sleepMs(int $milliseconds): void;
}
