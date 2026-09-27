<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer assistant
    |--------------------------------------------------------------------------
    |
    | env() is only read here: everywhere else the code asks config('assistant.*').
    |
    */

    'enabled' => (bool) env('ASSISTANT_ENABLED', true),

    'provider' => env('ASSISTANT_PROVIDER', 'gemini'),

    'model' => env('ASSISTANT_MODEL'),

    // The bot is a regular user without chat.viewAll. It is looked up by e-mail.
    'bot_email' => env('ASSISTANT_BOT_EMAIL', 'assistant@moonery.local'),

    'max_iterations' => (int) env('ASSISTANT_MAX_ITERATIONS', 5),

    'max_output_tokens' => (int) env('ASSISTANT_MAX_OUTPUT_TOKENS', 1024),

    // Per HTTP request to the provider.
    'timeout_seconds' => (int) env('ASSISTANT_TIMEOUT_SECONDS', 20),

    // Whole run, including retries and the spacing between calls.
    'run_deadline_seconds' => (int) env('ASSISTANT_RUN_DEADLINE_SECONDS', 90),

    'max_input_chars' => (int) env('ASSISTANT_MAX_INPUT_CHARS', 2000),

    // How many of the latest messages of the conversation are sent as context.
    'history_messages' => (int) env('ASSISTANT_HISTORY_MESSAGES', 10),

    // Per user: at most `runs` assistant runs within `window_seconds`.
    'user_rate_limit' => [
        'runs' => (int) env('ASSISTANT_USER_MAX_RUNS', 10),
        'window_seconds' => (int) env('ASSISTANT_USER_WINDOW_SECONDS', 600),
    ],

    // Tokens (input + output) per day, across every user.
    'daily_token_cap' => (int) env('ASSISTANT_DAILY_TOKEN_CAP', 200000),

    // A pending cancellation stops being confirmable after this long.
    'confirmation_ttl_minutes' => (int) env('ASSISTANT_CONFIRMATION_TTL_MINUTES', 10),

    'messages' => [
        'fallback' => "I couldn't sort this out right now. I've forwarded your conversation to Support, who will reply shortly.",
        'handoff' => "I'll forward your conversation to Support, who will reply shortly.",
        // :tracking_code is replaced by the code of the delivery.
        'canceled' => 'Done, delivery :tracking_code has been canceled.',
        'kept' => "No problem, I kept delivery :tracking_code as it was.",
        'cancel_failed' => "I couldn't cancel delivery :tracking_code because its status changed. Reach out to Support if you need help.",
        'confirm_cancel' => 'Do you confirm canceling delivery :tracking_code? Use the buttons below to confirm or keep the delivery.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | min_interval_ms      minimum wait between two calls, for EVERY call of the tool loop
    | max_retries          retries on 429/5xx/timeouts, with exponential backoff and jitter
    | daily_cap            provider calls per day; once reached the provider is not called
    | respect_retry_after  use the delay the provider suggests on a 429, when there is one
    | reset_timezone       where the provider's daily quota rolls over
    |
    */

    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'min_interval_ms' => (int) env('ASSISTANT_GEMINI_MIN_INTERVAL_MS', 5000),
            'max_retries' => (int) env('ASSISTANT_GEMINI_MAX_RETRIES', 3),
            'daily_cap' => (int) env('ASSISTANT_GEMINI_DAILY_CAP', 200),
            'respect_retry_after' => (bool) env('ASSISTANT_GEMINI_RESPECT_RETRY_AFTER', true),
            'reset_timezone' => env('ASSISTANT_GEMINI_RESET_TIMEZONE', 'America/Los_Angeles'),
        ],
    ],

];
