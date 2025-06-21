<?php

namespace App\Repositories;

use App\Models\Log;

class LogRepository
{
    public function create(array $data): Log
    {
        return Log::create($data);
    }
}
