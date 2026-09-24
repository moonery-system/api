<?php

namespace App\Assistant\Llm;

final class ToolDefinition
{
    /**
     * @param array<string, mixed> $parameters a minimal JSON schema: an object with properties
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters = ['type' => 'object', 'properties' => []],
    ) {}
}
