<?php

namespace App\Assistant\Llm\Exceptions;

class LlmProviderException extends LlmException
{
    public function __construct(string $message, public readonly bool $retryable = false)
    {
        parent::__construct($message);
    }
}
