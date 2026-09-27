<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retry ladder
    |--------------------------------------------------------------------------
    |
    | Delay, in milliseconds, of each retry tier a queue's consumer waits before trying a
    | failed message again. A message that fails past the last tier is dead-lettered.
    | env() is only read here: everywhere else the code asks config('messaging.retry.tiers_ms').
    |
    */

    'retry' => [
        'tiers_ms' => [
            (int) env('MESSAGING_RETRY_TIER_1_MS', 5000),
            (int) env('MESSAGING_RETRY_TIER_2_MS', 30000),
            (int) env('MESSAGING_RETRY_TIER_3_MS', 300000),
        ],
    ],

];
