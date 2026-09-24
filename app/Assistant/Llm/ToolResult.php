<?php

namespace App\Assistant\Llm;

final class ToolResult
{
    /**
     * @param array<string, mixed> $content
     */
    public function __construct(
        public readonly string $callId,
        public readonly string $name,
        public readonly array $content,
        public readonly bool $isError = false,
    ) {}
}
