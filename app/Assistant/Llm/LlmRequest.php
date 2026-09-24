<?php

namespace App\Assistant\Llm;

final class LlmRequest
{
    /**
     * @param array<int, LlmMessage> $messages
     * @param array<int, ToolDefinition> $tools
     * @param int|null $deadlineMs absolute time (ms since the epoch) after which the call must
     *        not even start waiting; the guard refuses instead of sleeping past it
     */
    public function __construct(
        public readonly string $systemPrompt,
        public readonly array $messages,
        public readonly array $tools = [],
        public readonly int $maxTokens = 1024,
        public readonly ?int $deadlineMs = null,
    ) {}

    /**
     * The same request with the conversation so far extended. Used by the tool loop.
     *
     * @param array<int, LlmMessage> $messages
     */
    public function withMessages(array $messages): self
    {
        return new self($this->systemPrompt, $messages, $this->tools, $this->maxTokens, $this->deadlineMs);
    }
}
