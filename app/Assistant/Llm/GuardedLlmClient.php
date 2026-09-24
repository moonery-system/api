<?php

namespace App\Assistant\Llm;

use App\Assistant\Llm\Exceptions\LlmDailyCapReachedException;
use App\Assistant\Llm\Exceptions\LlmDeadlineExceededException;
use App\Assistant\Llm\Exceptions\LlmProviderException;
use App\Assistant\Llm\Exceptions\LlmRateLimitException;
use App\Assistant\Llm\Exceptions\LlmTimeoutException;
use App\Assistant\Support\Clock;
use App\Assistant\Support\Sleeper;
use App\Contracts\Repositories\AssistantUsageInterface;
use Carbon\Carbon;
use Closure;

/**
 * Decorator that keeps a provider within its limits, whatever the provider is.
 *
 * Everything that is about *how often* and *how many times* lives here, so that the
 * provider classes only translate formats:
 *
 *  - min_interval_ms: the wait between two calls counts for EVERY call of the tool
 *    loop, not per message;
 *  - retries with exponential backoff and jitter on 429, 5xx and timeouts, honouring
 *    the delay the provider suggests when respect_retry_after is on;
 *  - daily caps (calls and tokens): once reached, the provider is not called at all.
 *
 * The spacing is kept in memory, which is enough because a single consumer makes all
 * the calls. More than one consumer would need a lock around it.
 */
class GuardedLlmClient implements LlmClient
{
    private const BACKOFF_BASE_MS = 1000;
    private const BACKOFF_CAP_MS = 30000;

    private ?int $lastCallMs = null;

    /**
     * @param array{min_interval_ms: int, max_retries: int, daily_cap: int, respect_retry_after: bool, reset_timezone: string} $settings
     * @param Closure|null $random returns a float in [0, 1); replaced in tests
     */
    public function __construct(
        private LlmClient $inner,
        private AssistantUsageInterface $usage,
        private Clock $clock,
        private Sleeper $sleeper,
        private array $settings,
        private int $dailyTokenCap,
        private ?Closure $random = null,
    ) {}

    public function provider(): string
    {
        return $this->inner->provider();
    }

    public function model(): string
    {
        return $this->inner->model();
    }

    public function generate(LlmRequest $request): LlmResponse
    {
        $attempt = 0;

        while (true) {
            $day = $this->today();

            $this->assertUnderDailyCaps($day);
            $this->waitForMinInterval($request->deadlineMs);

            // Every attempt is a call against the provider's quota, retries included.
            $this->usage->incrementCalls($this->provider(), $day);
            $this->lastCallMs = $this->clock->nowMs();

            try {
                $response = $this->inner->generate($request);

                $this->usage->addTokens($this->provider(), $day, $response->usage->inputTokens, $response->usage->outputTokens);

                return $response;
            } catch (LlmRateLimitException|LlmTimeoutException|LlmProviderException $e) {
                if ($e instanceof LlmProviderException && !$e->retryable) throw $e;

                if ($attempt >= $this->settings['max_retries']) throw $e;

                $this->sleepBeforeDeadline($this->retryDelayMs($attempt, $e), $request->deadlineMs);

                $attempt++;
            }
        }
    }

    private function assertUnderDailyCaps(string $day): void
    {
        $callCap = $this->settings['daily_cap'];

        if ($callCap > 0 && $this->usage->callsOn($this->provider(), $day) >= $callCap) {
            throw new LlmDailyCapReachedException('The daily limit of provider calls was reached.');
        }

        if ($this->dailyTokenCap > 0 && $this->usage->tokensOn($day) >= $this->dailyTokenCap) {
            throw new LlmDailyCapReachedException('The daily limit of tokens was reached.');
        }
    }

    private function waitForMinInterval(?int $deadlineMs): void
    {
        if ($this->lastCallMs === null) return;

        $wait = $this->settings['min_interval_ms'] - ($this->clock->nowMs() - $this->lastCallMs);

        if ($wait > 0) $this->sleepBeforeDeadline($wait, $deadlineMs);
    }

    /**
     * Sleeping past the deadline is pointless: the run would be over by the time the
     * call happened. Refuse instead.
     */
    private function sleepBeforeDeadline(int $milliseconds, ?int $deadlineMs): void
    {
        if ($deadlineMs !== null && $this->clock->nowMs() + $milliseconds > $deadlineMs) {
            throw new LlmDeadlineExceededException('The run deadline would pass before the next call.');
        }

        $this->sleeper->sleepMs($milliseconds);
    }

    private function retryDelayMs(int $attempt, \Throwable $error): int
    {
        if (
            $error instanceof LlmRateLimitException
            && $error->retryAfterMs !== null
            && $this->settings['respect_retry_after']
        ) {
            return $error->retryAfterMs;
        }

        $ceiling = min(self::BACKOFF_CAP_MS, self::BACKOFF_BASE_MS * (2 ** $attempt));

        // Jitter between half and all of the ceiling, so a burst of retries spreads out.
        return (int) round($ceiling * (0.5 + 0.5 * $this->jitter()));
    }

    private function jitter(): float
    {
        return $this->random ? ($this->random)() : mt_rand() / (mt_getrandmax() + 1);
    }

    /**
     * The provider's day, not ours: its quota rolls over on its own timezone.
     */
    private function today(): string
    {
        return Carbon::createFromTimestampMs($this->clock->nowMs(), $this->settings['reset_timezone'])->toDateString();
    }
}
