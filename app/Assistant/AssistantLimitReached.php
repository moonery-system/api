<?php

namespace App\Assistant;

use RuntimeException;

/**
 * A limit of ours (not the provider's) stopped the run: the per-user rate limit, the
 * iteration cap, or an answer with nothing in it. The reason is what the handoff records.
 */
class AssistantLimitReached extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
