<?php

namespace App\Assistant\Llm;

/**
 * The model asking for a tool. The arguments are whatever the model wrote: they are
 * untrusted input and every tool validates them.
 */
final class ToolCall
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments = [],
    ) {}
}
