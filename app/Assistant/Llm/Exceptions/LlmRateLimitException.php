<?php

namespace App\Assistant\Llm\Exceptions;

class LlmRateLimitException extends LlmException
{
    public function __construct(string $message = 'The provider rate limit was hit.', public readonly ?int $retryAfterMs = null)
    {
        parent::__construct($message);
    }
}
