<?php

namespace App\Messaging;

/**
 * One policy, reused by every consumer that retries: how many tiers there are, how long
 * each waits, and what turns a failure into a dead-letter instead of another try.
 *
 * It touches no I/O -- given a throwable and how many times the message already failed
 * before now, it returns a decision. The consumer is the one that publishes the message
 * onward; this class only decides where.
 */
class RetryPolicy
{
    /**
     * @param array<int, int> $tiersMs delay in milliseconds for each tier, in order (tier 1 first)
     */
    public function __construct(
        private array $tiersMs,
    ) {}

    /**
     * @param int $priorFailures how many times this message has already failed before this attempt (0 the first time it fails)
     */
    public function decide(\Throwable $error, int $priorFailures): RetryDecision
    {
        if ($error instanceof PermanentFailureException) {
            return RetryDecision::deadLetter();
        }

        if ($priorFailures >= count($this->tiersMs)) {
            return RetryDecision::deadLetter();
        }

        $tierNumber = $priorFailures + 1;

        return RetryDecision::retry("retry.{$tierNumber}", $this->tiersMs[$priorFailures]);
    }

    /**
     * How many tiers this policy has, i.e. the maximum number of retries before a
     * transient failure is given up on.
     */
    public function maxRetries(): int
    {
        return count($this->tiersMs);
    }
}
