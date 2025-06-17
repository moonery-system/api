<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\Log;

class LogService
{
    public function record(LogEventTypeEnum $eventType, $context = [])
    {
        $user = auth()->id();

        Log::create([
            'user_id' => $user,
            'event_type' => $eventType->value,
            'context' =>  $context,
        ]);
    }
}
