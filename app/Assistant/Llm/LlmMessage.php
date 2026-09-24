<?php

namespace App\Assistant\Llm;

final class LlmMessage
{
    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_TOOL = 'tool';

    /**
     * @param array<int, ToolCall> $toolCalls set on an assistant message that asked for tools
     * @param array<int, ToolResult> $toolResults set on a tool message
     * @param array<string, mixed>|null $providerState what the provider needs handed back untouched
     *        (for Gemini, the raw parts of its turn, which carry the thought signatures)
     */
    public function __construct(
        public readonly string $role,
        public readonly ?string $text = null,
        public readonly array $toolCalls = [],
        public readonly array $toolResults = [],
        public readonly ?array $providerState = null,
    ) {}

    public static function user(string $text): self
    {
        return new self(self::ROLE_USER, text: $text);
    }

    public static function assistant(string $text): self
    {
        return new self(self::ROLE_ASSISTANT, text: $text);
    }

    public static function fromResponse(LlmResponse $response): self
    {
        return new self(
            self::ROLE_ASSISTANT,
            text: $response->text,
            toolCalls: $response->toolCalls,
            providerState: $response->providerState,
        );
    }

    /**
     * @param array<int, ToolResult> $results
     */
    public static function toolResults(array $results): self
    {
        return new self(self::ROLE_TOOL, toolResults: $results);
    }
}
