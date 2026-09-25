<?php

namespace App\Assistant\Tools;

use App\Models\Conversation;
use App\Models\User;

/**
 * Who the tools work for. The user comes from the conversation -- never from what the
 * model wrote in an argument -- so no tool can be pointed at somebody else.
 *
 * It is also how a tool reports a side effect to the runner: a tool does not write to
 * the chat, it only records here that a confirmation was requested or a handoff wanted.
 */
class ToolContext
{
    public ?int $pendingActionId = null;
    public ?string $pendingTrackingCode = null;

    public ?string $handoffNote = null;

    public function __construct(
        public readonly User $user,
        public readonly Conversation $conversation,
    ) {}

    public function handoffRequested(): bool
    {
        return $this->handoffNote !== null;
    }
}
