<?php

namespace App\Assistant\Llm;

final class LlmResponse
{
    /**
     * @param array<int, ToolCall> $toolCalls
     * @param array<string, mixed>|null $providerState
     */
    public function __construct(
        public readonly ?string $text = null,
        public readonly array $toolCalls = [],
        public readonly Usage $usage = new Usage(),
        public readonly string $finishReason = 'stop',
        public readonly ?array $providerState = null,
    ) {}

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
