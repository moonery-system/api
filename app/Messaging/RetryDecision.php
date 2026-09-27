<?php

namespace App\Messaging;

/**
 * What a consumer should do about one failed message: try again after a delay, on a named
 * tier, or give up and dead-letter it. Immutable, and built only through its two named
 * constructors, so a decision is never in an inconsistent half-state.
 */
final class RetryDecision
{
    private function __construct(
        public readonly bool $shouldRetry,
        public readonly ?string $tier,
        public readonly ?int $delayMs,
    ) {}

    public static function retry(string $tier, int $delayMs): self
    {
        return new self(true, $tier, $delayMs);
    }

    public static function deadLetter(): self
    {
        return new self(false, null, null);
    }
}
