<?php

namespace Tests\Feature;

use App\Assistant\Llm\Exceptions\LlmAuthException;
use App\Assistant\Llm\Exceptions\LlmDailyCapReachedException;
use App\Assistant\Llm\Exceptions\LlmDeadlineExceededException;
use App\Assistant\Llm\Exceptions\LlmProviderException;
use App\Assistant\Llm\Exceptions\LlmRateLimitException;
use App\Assistant\Llm\Exceptions\LlmTimeoutException;
use App\Assistant\Llm\GuardedLlmClient;
use App\Assistant\Llm\LlmMessage;
use App\Assistant\Llm\LlmRequest;
use App\Contracts\Repositories\AssistantUsageInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeClock;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class GuardedLlmClientTest extends TestCase
{
    use RefreshDatabase;

    private FakeClock $clock;
    private AssistantUsageInterface $usage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FakeClock();
        $this->usage = app(AssistantUsageInterface::class);
    }

    private function guard(FakeLlmClient $inner, array $settings = [], int $tokenCap = 0): GuardedLlmClient
    {
        return new GuardedLlmClient(
            inner: $inner,
            usage: $this->usage,
            clock: $this->clock,
            sleeper: $this->clock,
            settings: array_merge([
                'min_interval_ms' => 0,
                'max_retries' => 3,
                'daily_cap' => 0,
                'respect_retry_after' => true,
                'reset_timezone' => 'America/Los_Angeles',
            ], $settings),
            dailyTokenCap: $tokenCap,
            // No jitter: the delay is the full ceiling, so the expectations are exact.
            random: fn() => 1.0,
        );
    }

    private function request(?int $deadlineMs = null): LlmRequest
    {
        return new LlmRequest('system', [LlmMessage::user('oi')], deadlineMs: $deadlineMs);
    }

    private function today(): string
    {
        return \Carbon\Carbon::createFromTimestampMs($this->clock->nowMs(), 'America/Los_Angeles')->toDateString();
    }

    public function test_a_429_is_retried_with_exponential_backoff(): void
    {
        $inner = new FakeLlmClient([
            new LlmRateLimitException(),
            new LlmRateLimitException(),
            FakeLlmClient::text('pronto'),
        ]);

        $response = $this->guard($inner)->generate($this->request());

        $this->assertSame('pronto', $response->text);
        $this->assertSame(3, $inner->calls());
        $this->assertSame([1000, 2000], $this->clock->sleeps);
    }

    public function test_the_delay_the_provider_suggests_wins_over_the_backoff(): void
    {
        $inner = new FakeLlmClient([new LlmRateLimitException(retryAfterMs: 7000), FakeLlmClient::text('ok')]);

        $this->guard($inner)->generate($this->request());

        $this->assertSame([7000], $this->clock->sleeps);
    }

    public function test_the_suggested_delay_is_ignored_when_told_not_to_respect_it(): void
    {
        $inner = new FakeLlmClient([new LlmRateLimitException(retryAfterMs: 7000), FakeLlmClient::text('ok')]);

        $this->guard($inner, ['respect_retry_after' => false])->generate($this->request());

        $this->assertSame([1000], $this->clock->sleeps);
    }

    public function test_the_error_comes_out_once_the_retries_are_spent(): void
    {
        $inner = new FakeLlmClient([
            new LlmTimeoutException(), new LlmTimeoutException(), new LlmTimeoutException(),
        ]);

        try {
            $this->guard($inner, ['max_retries' => 2])->generate($this->request());
            $this->fail('the timeout should have come out');
        } catch (LlmTimeoutException) {
            // 1 attempt + 2 retries
            $this->assertSame(3, $inner->calls());
        }
    }

    public function test_a_server_error_is_retried_but_a_client_error_is_not(): void
    {
        $retryable = new FakeLlmClient([new LlmProviderException('503', retryable: true), FakeLlmClient::text('ok')]);
        $this->guard($retryable)->generate($this->request());
        $this->assertSame(2, $retryable->calls());

        $fatal = new FakeLlmClient([new LlmProviderException('bad request', retryable: false)]);
        try {
            $this->guard($fatal)->generate($this->request());
            $this->fail('should not retry');
        } catch (LlmProviderException) {
            $this->assertSame(1, $fatal->calls());
        }
    }

    public function test_an_authentication_failure_is_never_retried(): void
    {
        $inner = new FakeLlmClient([new LlmAuthException('key refused')]);

        try {
            $this->guard($inner)->generate($this->request());
            $this->fail('should not retry');
        } catch (LlmAuthException) {
            $this->assertSame(1, $inner->calls());
            $this->assertSame([], $this->clock->sleeps);
        }
    }

    public function test_the_minimum_interval_applies_to_every_call_of_the_loop(): void
    {
        $inner = new FakeLlmClient([FakeLlmClient::text('a'), FakeLlmClient::text('b'), FakeLlmClient::text('c')]);
        $guard = $this->guard($inner, ['min_interval_ms' => 5000]);

        $guard->generate($this->request());   // first call: nothing to wait for
        $this->clock->advance(1000);
        $guard->generate($this->request());   // 1s went by, so 4s are missing
        $guard->generate($this->request());   // no time went by: the whole 5s

        $this->assertSame([4000, 5000], $this->clock->sleeps);
    }

    public function test_no_wait_when_the_interval_already_went_by(): void
    {
        $inner = new FakeLlmClient([FakeLlmClient::text('a'), FakeLlmClient::text('b')]);
        $guard = $this->guard($inner, ['min_interval_ms' => 5000]);

        $guard->generate($this->request());
        $this->clock->advance(6000);
        $guard->generate($this->request());

        $this->assertSame([], $this->clock->sleeps);
    }

    public function test_the_daily_cap_stops_the_provider_from_being_called(): void
    {
        $inner = new FakeLlmClient([FakeLlmClient::text('never')]);

        for ($i = 0; $i < 2; $i++) $this->usage->incrementCalls('fake', $this->today());

        try {
            $this->guard($inner, ['daily_cap' => 2])->generate($this->request());
            $this->fail('the cap should have stopped the call');
        } catch (LlmDailyCapReachedException) {
            $this->assertSame(0, $inner->calls());
        }
    }

    public function test_every_attempt_counts_against_the_daily_cap_retries_included(): void
    {
        $inner = new FakeLlmClient([new LlmRateLimitException(), new LlmRateLimitException(), FakeLlmClient::text('ok')]);

        $this->guard($inner, ['daily_cap' => 100])->generate($this->request());

        $this->assertSame(3, $this->usage->callsOn('fake', $this->today()));
    }

    public function test_the_cap_can_be_reached_in_the_middle_of_the_retries(): void
    {
        $inner = new FakeLlmClient([new LlmRateLimitException(), new LlmRateLimitException(), FakeLlmClient::text('ok')]);

        $this->expectException(LlmDailyCapReachedException::class);

        $this->guard($inner, ['daily_cap' => 2])->generate($this->request());
    }

    public function test_the_daily_token_cap_stops_the_provider_from_being_called(): void
    {
        $inner = new FakeLlmClient([FakeLlmClient::text('never')]);

        $this->usage->addTokens('fake', $this->today(), 600, 500);

        try {
            $this->guard($inner, [], tokenCap: 1000)->generate($this->request());
            $this->fail('the token cap should have stopped the call');
        } catch (LlmDailyCapReachedException) {
            $this->assertSame(0, $inner->calls());
        }
    }

    public function test_tokens_of_a_call_are_added_to_the_day(): void
    {
        $inner = new FakeLlmClient([FakeLlmClient::text('ok', input: 120, output: 30)]);

        $this->guard($inner)->generate($this->request());

        $this->assertSame(150, $this->usage->tokensOn($this->today()));
    }

    public function test_the_counters_roll_over_on_the_provider_day_not_ours(): void
    {
        // 07:59 UTC is 23:59 of the day before in Los Angeles (PST, UTC-8 until DST starts on
        // the 14th); two minutes later it is a new day there, while UTC is still on 2027-03-10.
        $this->clock = new FakeClock(strtotime('2027-03-10 07:59:00 UTC') * 1000);
        $inner = new FakeLlmClient([FakeLlmClient::text('a'), FakeLlmClient::text('b')]);
        $guard = $this->guard($inner);

        $guard->generate($this->request());
        $this->clock->advance(120_000);
        $guard->generate($this->request());

        $this->assertSame(1, $this->usage->callsOn('fake', '2027-03-09'));
        $this->assertSame(1, $this->usage->callsOn('fake', '2027-03-10'));
    }

    public function test_it_refuses_to_sleep_past_the_deadline(): void
    {
        $inner = new FakeLlmClient([new LlmRateLimitException(), FakeLlmClient::text('never')]);

        $this->expectException(LlmDeadlineExceededException::class);

        // The backoff asks for 1s but only 500ms are left.
        $this->guard($inner)->generate($this->request(deadlineMs: $this->clock->nowMs() + 500));
    }
}
