<?php

namespace App\Contracts\Repositories;

use App\Models\Log;

interface LogInterface
{
    public function create(array $data): Log;
}
