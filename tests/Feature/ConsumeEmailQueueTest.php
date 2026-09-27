<?php

namespace Tests\Feature;

use App\Console\Commands\ConsumeEmailQueue;
use App\Mail\PasswordResetMail;
use App\Models\Invite;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RuntimeException;
use Tests\TestCase;

/**
 * process(AMQPMessage) never touches ack()/the channel, so every message here is built
 * bare, with no channel attached -- exactly what a redelivered message looks like once it
 * bounces off a retry-tier queue: same body and headers, but its routing key (delivery
 * metadata, not part of the body) becomes the MAIN queue's own name, because that hop
 * happens through the default exchange. bounced() builds exactly that.
 */
class ConsumeEmailQueueTest extends TestCase
{
    use RefreshDatabase;

    private const MAIN_QUEUE = 'emails.queue';

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function invite(array $overrides = []): Invite
    {
        return Invite::create($overrides + [
            'token' => Str::random(60),
            'user_id' => User::factory()->create()->id,
            'expires_at' => now()->addHour(),
        ]);
    }

    private function message(string $routingKey, array $body, array $headers = []): AMQPMessage
    {
        $properties = ['content_type' => 'application/json', 'delivery_mode' => 2];

        if ($headers) $properties['application_headers'] = new AMQPTable($headers);

        $message = new AMQPMessage(json_encode($body), $properties);
        $message->setDeliveryInfo(1, false, 'delivery.events', $routingKey);

        return $message;
    }

    private function rawMessage(string $routingKey, string $rawBody): AMQPMessage
    {
        $message = new AMQPMessage($rawBody, ['content_type' => 'application/json']);
        $message->setDeliveryInfo(1, false, 'delivery.events', $routingKey);

        return $message;
    }

    /**
     * Builds the message the way it looks after bouncing off a retry-tier queue: routing
     * key = the main queue's own name, body and headers exactly as published.
     *
     * @param array{queue: string, data: array, headers: array} $publication
     */
    private function bounced(array $publication): AMQPMessage
    {
        return $this->message(self::MAIN_QUEUE, $publication['data'], $publication['headers']);
    }

    private function command(): ConsumeEmailQueue
    {
        return app(ConsumeEmailQueue::class);
    }

    private function pivotEmailedAt(int $notificationId, int $userId): ?string
    {
        return DB::table('user_notifications')
            ->where('notification_id', $notificationId)
            ->where('user_id', $userId)
            ->value('emailed_at');
    }

    // ---- happy path, and what "handled" looks like ---------------------------------------

    public function test_a_first_time_success_sends_the_invite_and_marks_it(): void
    {
        $invite = $this->invite();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once();

        $out = $this->command()->process($this->message('invites.email', ['invite_id' => $invite->id]));

        $this->assertStringContainsString('Handled invites.email', $out);
        $this->assertNotNull($invite->fresh()->email_sent_at);
    }

    public function test_the_password_reset_key_sends_the_password_reset_mail(): void
    {
        $invite = $this->invite();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->with(Mockery::type(PasswordResetMail::class));

        $out = $this->command()->process($this->message('password-resets.email', ['invite_id' => $invite->id]));

        $this->assertStringContainsString('Handled password-resets.email', $out);
        $this->assertNotNull($invite->fresh()->email_sent_at);
    }

    // ---- retry: escalating tiers, then success, exactly one e-mail ------------------------

    public function test_a_message_that_fails_twice_and_succeeds_on_the_third_try_sends_exactly_one_email(): void
    {
        $invite = $this->invite();
        $broker = $this->fakeBroker();

        $failuresLeft = 2;
        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->times(3)->andReturnUsing(function () use (&$failuresLeft) {
            if ($failuresLeft > 0) {
                $failuresLeft--;
                throw new RuntimeException('smtp down');
            }
        });

        $body = ['invite_id' => $invite->id];
        $command = $this->command();

        $first = $command->process($this->message('invites.email', $body));
        $this->assertStringContainsString('Retrying invites.email on retry.1', $first);
        $this->assertCount(1, $broker->publishedToQueue);
        $this->assertSame('emails.queue.retry.1', $broker->publishedToQueue[0]['queue']);
        $this->assertSame(1, $broker->publishedToQueue[0]['headers']['x-retry-attempt']);
        $this->assertSame('invites.email', $broker->publishedToQueue[0]['headers']['x-original-routing-key']);
        $this->assertSame($body, $broker->publishedToQueue[0]['data']);

        $second = $command->process($this->bounced($broker->publishedToQueue[0]));
        $this->assertStringContainsString('Retrying invites.email on retry.2', $second);
        $this->assertCount(2, $broker->publishedToQueue);
        $this->assertSame('emails.queue.retry.2', $broker->publishedToQueue[1]['queue']);
        $this->assertSame(2, $broker->publishedToQueue[1]['headers']['x-retry-attempt']);

        $third = $command->process($this->bounced($broker->publishedToQueue[1]));
        $this->assertStringContainsString('Handled invites.email', $third);
        $this->assertCount(2, $broker->publishedToQueue, 'a success must not publish anywhere');
        $this->assertNotNull($invite->fresh()->email_sent_at);
    }

    public function test_the_original_routing_key_survives_the_bounce_through_a_retry_queue(): void
    {
        // Delivered as it would be after a bounce: routing key is the MAIN queue's own
        // name, and the real one only lives in the header. Without that header being
        // preferred, this would resolve to no handler and dead-letter instead of sending.
        $invite = $this->invite();
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once();

        $bounced = $this->message(self::MAIN_QUEUE, ['invite_id' => $invite->id], [
            'x-retry-attempt' => 1,
            'x-original-routing-key' => 'invites.email',
        ]);

        $out = $this->command()->process($bounced);

        $this->assertStringContainsString('Handled invites.email', $out);
        $this->assertNotNull($invite->fresh()->email_sent_at);
    }

    // ---- exhaustion: dead-lettered after the last tier -------------------------------------

    public function test_a_failure_that_outlives_every_tier_is_dead_lettered_with_the_full_envelope(): void
    {
        $invite = $this->invite();
        $broker = $this->fakeBroker();

        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->andThrow(new RuntimeException('smtp down'));

        $body = ['invite_id' => $invite->id];
        $command = $this->command();

        $out = $command->process($this->message('invites.email', $body));
        $this->assertStringContainsString('Retrying', $out);

        for ($tier = 1; $tier < 3; $tier++) {
            $last = $broker->publishedToQueue[array_key_last($broker->publishedToQueue)];
            $out = $command->process($this->bounced($last));
            $this->assertStringContainsString("Retrying invites.email on retry." . ($tier + 1), $out);
        }

        // The 4th attempt has exhausted all 3 tiers.
        $last = $broker->publishedToQueue[array_key_last($broker->publishedToQueue)];
        $out = $command->process($this->bounced($last));

        $this->assertStringContainsString('Dead-lettered invites.email', $out);

        $dlq = collect($broker->publishedToQueue)->firstWhere('queue', 'emails.queue.dlq');
        $this->assertNotNull($dlq);
        $this->assertSame('invites.email', $dlq['data']['original_routing_key']);
        $this->assertSame(4, $dlq['data']['attempts']);
        $this->assertSame($body, $dlq['data']['payload']);
        $this->assertStringContainsString('smtp down', $dlq['data']['error']);
        $this->assertArrayHasKey('failed_at', $dlq['data']);
        $this->assertNull($invite->fresh()->email_sent_at);
    }

    // ---- permanent failures: no retry tier spent -------------------------------------------

    public function test_a_body_that_is_not_valid_json_is_dead_lettered_without_ever_retrying(): void
    {
        $broker = $this->fakeBroker();

        $out = $this->command()->process($this->rawMessage('invites.email', '{not-json-at-all'));

        $this->assertStringContainsString('Dead-lettered invites.email', $out);
        $this->assertCount(1, $broker->publishedToQueue, 'nothing should have gone to a retry tier');
        $this->assertSame('emails.queue.dlq', $broker->publishedToQueue[0]['queue']);
        $this->assertSame(1, $broker->publishedToQueue[0]['data']['attempts']);
        $this->assertArrayHasKey('raw_body', $broker->publishedToQueue[0]['data']['payload']);
    }

    public function test_an_unknown_routing_key_is_dead_lettered_without_ever_retrying(): void
    {
        $broker = $this->fakeBroker();

        $out = $this->command()->process($this->message('something.unexpected', ['x' => 1]));

        $this->assertStringContainsString('Dead-lettered something.unexpected', $out);
        $this->assertCount(1, $broker->publishedToQueue);
        $this->assertSame('emails.queue.dlq', $broker->publishedToQueue[0]['queue']);
    }

    // ---- what stays a silent no-op, on purpose (see the decision log) ---------------------

    public function test_a_payload_missing_the_expected_id_is_a_silent_no_op_not_a_dead_letter(): void
    {
        $broker = $this->fakeBroker();
        Mail::shouldReceive('to')->never();

        $out = $this->command()->process($this->message('invites.email', []));

        $this->assertStringContainsString('Handled invites.email', $out);
        $this->assertSame([], $broker->publishedToQueue);
    }

    public function test_a_reference_to_an_invite_that_no_longer_exists_is_a_silent_no_op(): void
    {
        $broker = $this->fakeBroker();
        Mail::shouldReceive('to')->never();

        $out = $this->command()->process($this->message('invites.email', ['invite_id' => 999999]));

        $this->assertStringContainsString('Handled invites.email', $out);
        $this->assertSame([], $broker->publishedToQueue);
    }

    // ---- idempotency markers, exercised directly -------------------------------------------

    public function test_an_invite_already_emailed_is_never_sent_again(): void
    {
        $invite = $this->invite(['email_sent_at' => now()]);
        Mail::shouldReceive('to')->never();

        $out = $this->command()->process($this->message('invites.email', ['invite_id' => $invite->id]));

        $this->assertStringContainsString('Handled invites.email', $out);
    }

    public function test_a_partial_notification_failure_only_resends_to_the_recipient_still_pending(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $notification = Notification::create(['title' => 'Delivery update', 'description' => 'It moved']);
        $notification->users()->attach([$userA->id, $userB->id]);

        $sendCounts = [];
        Mail::shouldReceive('to')->andReturnUsing(function ($address) use ($userB, &$sendCounts) {
            return tap(Mockery::mock(), function ($pending) use ($address, $userB, &$sendCounts) {
                $pending->shouldReceive('send')->andReturnUsing(function () use ($address, $userB, &$sendCounts) {
                    $sendCounts[$address] = ($sendCounts[$address] ?? 0) + 1;

                    // B fails only the first time it is tried; A always goes through.
                    if ($address === $userB->email && $sendCounts[$address] === 1) {
                        throw new RuntimeException('smtp down');
                    }
                });
            });
        });

        $broker = $this->fakeBroker();
        $body = ['notification_id' => $notification->id];
        $command = $this->command();

        $first = $command->process($this->message('notifications.email', $body));
        $this->assertStringContainsString('Retrying notifications.email', $first);

        $this->assertNotNull($this->pivotEmailedAt($notification->id, $userA->id), 'A succeeded and must be marked');
        $this->assertNull($this->pivotEmailedAt($notification->id, $userB->id), 'B failed and must stay pending');

        // Republished with the same notification_id: the pending-recipient filter, not the
        // payload, is what keeps A from being mailed again.
        $this->assertSame($body, $broker->publishedToQueue[0]['data']);

        $second = $command->process($this->bounced($broker->publishedToQueue[0]));
        $this->assertStringContainsString('Handled notifications.email', $second);

        $this->assertSame(1, $sendCounts[$userA->email], 'A must never be mailed twice');
        $this->assertSame(2, $sendCounts[$userB->email], 'B: one failed try plus one that succeeded');
        $this->assertNotNull($this->pivotEmailedAt($notification->id, $userB->id));
    }

    public function test_a_notification_that_no_longer_exists_is_a_silent_no_op(): void
    {
        $broker = $this->fakeBroker();
        Mail::shouldReceive('to')->never();

        $out = $this->command()->process($this->message('notifications.email', ['notification_id' => 999999]));

        $this->assertStringContainsString('Handled notifications.email', $out);
        $this->assertSame([], $broker->publishedToQueue);
    }
}
