<?php

namespace App\Assistant\Llm;

final class Usage
{
    public function __construct(
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
    ) {}

    public function total(): int
    {
        return $this->inputTokens + $this->outputTokens;
    }
}
