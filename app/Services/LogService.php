<?php

namespace App\Services;

use App\Contracts\Repositories\LogInterface;
use App\Enums\LogEventTypeEnum;

class LogService
{
    public function __construct(
        private LogInterface $logRepository
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
