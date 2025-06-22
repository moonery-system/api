<?php

namespace App\Repositories;

use App\Contracts\Repositories\LogInterface;
use App\Models\Log;

class LogRepository implements LogInterface
{
    public function create(array $data): Log
    {
        return Log::create($data);
    }
}
