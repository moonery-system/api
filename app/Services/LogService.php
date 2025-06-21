<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\Log;
use App\Repositories\LogRepository;

class LogService
{
    public function __construct(
        private LogRepository $logRepository
    ){}

    public function record(LogEventTypeEnum $eventType, $context = []): void
    {
        $user = auth()->id();

        $this->logRepository->create([
            'user_id' => $user,
            'event_type' => $eventType->value,
            'context' =>  $context,
        ]);
    }
}
