<?php

namespace Tests\Feature;

use App\Messaging\PermanentFailureException;
use App\Messaging\RetryPolicy;
use RuntimeException;
use Tests\TestCase;

class RetryPolicyTest extends TestCase
{
    private function policy(): RetryPolicy
    {
        // Same shape as config/messaging.php: three tiers, escalating.
        return new RetryPolicy([5000, 30000, 300000]);
    }

    public function test_a_transient_failure_is_retried_on_the_first_tier(): void
    {
        $decision = $this->policy()->decide(new RuntimeException('smtp down'), priorFailures: 0);

        $this->assertTrue($decision->shouldRetry);
        $this->assertSame('retry.1', $decision->tier);
        $this->assertSame(5000, $decision->delayMs);
    }

    public function test_each_further_failure_escalates_to_the_next_tier(): void
    {
        $policy = $this->policy();

        $second = $policy->decide(new RuntimeException('smtp down'), priorFailures: 1);
        $this->assertSame('retry.2', $second->tier);
        $this->assertSame(30000, $second->delayMs);

        $third = $policy->decide(new RuntimeException('smtp down'), priorFailures: 2);
        $this->assertSame('retry.3', $third->tier);
        $this->assertSame(300000, $third->delayMs);
    }

    public function test_a_failure_past_the_last_tier_is_dead_lettered(): void
    {
        $decision = $this->policy()->decide(new RuntimeException('smtp down'), priorFailures: 3);

        $this->assertFalse($decision->shouldRetry);
        $this->assertNull($decision->tier);
        $this->assertNull($decision->delayMs);
    }

    public function test_a_permanent_failure_is_dead_lettered_even_on_the_first_try(): void
    {
        $decision = $this->policy()->decide(new PermanentFailureException('bad json'), priorFailures: 0);

        $this->assertFalse($decision->shouldRetry);
    }

    public function test_a_permanent_failure_is_dead_lettered_even_with_tiers_still_left(): void
    {
        $decision = $this->policy()->decide(new PermanentFailureException('bad json'), priorFailures: 2);

        $this->assertFalse($decision->shouldRetry);
    }

    public function test_max_retries_matches_the_number_of_tiers(): void
    {
        $this->assertSame(3, $this->policy()->maxRetries());
        $this->assertSame(1, (new RetryPolicy([1000]))->maxRetries());
    }

    public function test_a_policy_with_no_tiers_dead_letters_every_transient_failure_immediately(): void
    {
        $decision = (new RetryPolicy([]))->decide(new RuntimeException('down'), priorFailures: 0);

        $this->assertFalse($decision->shouldRetry);
    }
}
