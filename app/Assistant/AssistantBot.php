<?php

namespace App\Assistant;

use App\Contracts\Repositories\UserInterface;

/**
 * Who the assistant is, as a user id. Looked up once per instance (the container hands out a
 * single one per request), so marking every message of a conversation costs one query.
 *
 * A miss is not remembered: the bot may be seeded after the first lookup.
 */
class AssistantBot
{
    private ?int $id = null;

    public function __construct(
        private UserInterface $userRepository
    ) {}

    public function id(): ?int
    {
        return $this->id ??= $this->userRepository->findByEmail((string) config('assistant.bot_email'))?->id;
    }
}
