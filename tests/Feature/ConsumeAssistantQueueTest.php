<?php

namespace Tests\Feature;

use App\Assistant\AssistantRunner;
use App\Assistant\Llm\LlmClient;
use App\Console\Commands\ConsumeAssistantQueue;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\Support\FakeLlmClient;
use Tests\Support\FakeRabbitMQPublisher;
use Tests\TestCase;

class ConsumeAssistantQueueTest extends TestCase
{
    use RefreshDatabase;

    private FakeRabbitMQPublisher $broker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->broker = $this->fakeBroker();

        config()->set('assistant.enabled', true);
        config()->set('assistant.model', 'fake-model');
    }

    public function test_a_payload_with_a_message_id_is_answered(): void
    {
        $this->app->instance(LlmClient::class, new FakeLlmClient([FakeLlmClient::text('resposta do consumidor')]));

        $alice = $this->client();
        $conversation = Conversation::create(['user_id' => $alice->id]);
        $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $alice->id, 'body' => 'oi']);

        app(ConsumeAssistantQueue::class)->process(['message_id' => $message->id]);

        $bot = User::where('email', config('assistant.bot_email'))->firstOrFail();
        $this->assertSame(
            'resposta do consumidor',
            Message::where('conversation_id', $conversation->id)->where('sender_id', $bot->id)->sole()->body
        );
    }

    public function test_garbage_payloads_are_ignored_without_touching_the_provider(): void
    {
        $llm = new FakeLlmClient();
        $this->app->instance(LlmClient::class, $llm);

        $command = app(ConsumeAssistantQueue::class);

        $command->process([]);
        $command->process(['message_id' => 'abc']);
        $command->process(['message_id' => 999999]);   // a message that does not exist

        $this->assertSame(0, $llm->calls());
    }

    public function test_a_crash_inside_the_run_does_not_escape_the_consumer(): void
    {
        $this->app->instance(LlmClient::class, new FakeLlmClient([new \Error('something unforeseen')]));

        $alice = $this->client();
        $conversation = Conversation::create(['user_id' => $alice->id]);
        $message = Message::create(['conversation_id' => $conversation->id, 'sender_id' => $alice->id, 'body' => 'oi']);

        // Would be a fatal in a long-lived process if it escaped.
        app(ConsumeAssistantQueue::class)->process(['message_id' => $message->id]);

        // The runner turns even that into a fallback and a handoff.
        $this->assertSame(Conversation::ASSISTANT_HANDED_OFF, $conversation->fresh()->assistant_status);
    }

    // ---- what happens BEFORE the runner's own safety net, e.g. a database blip -----------

    /**
     * Replaces AssistantRunner with a mock whose handle() throws directly: this is the only
     * way to exercise process()'s own try/catch, since the real runner already absorbs
     * everything from claiming the run onward into a fallback + handoff.
     */
    private function runnerThatAlwaysThrows(\Throwable $error): void
    {
        $runner = Mockery::mock(AssistantRunner::class);
        $runner->shouldReceive('handle')->andThrow($error);

        $this->app->instance(AssistantRunner::class, $runner);
    }

    public function test_a_failure_claiming_the_run_is_retried_on_the_first_tier(): void
    {
        $this->runnerThatAlwaysThrows(new RuntimeException('could not reach the database'));

        $out = app(ConsumeAssistantQueue::class)->process(['message_id' => 42]);

        $this->assertStringContainsString('Retrying message 42 on retry.1', $out);
        $this->assertCount(1, $this->broker->publishedToQueue);
        $this->assertSame('assistant.queue.retry.1', $this->broker->publishedToQueue[0]['queue']);
        $this->assertSame(1, $this->broker->publishedToQueue[0]['headers']['x-retry-attempt']);
        $this->assertSame(['message_id' => 42], $this->broker->publishedToQueue[0]['data']);
    }

    public function test_it_escalates_tiers_and_dead_letters_once_they_are_exhausted(): void
    {
        $this->runnerThatAlwaysThrows(new RuntimeException('could not reach the database'));

        $command = app(ConsumeAssistantQueue::class);
        $data = ['message_id' => 42];

        $out = $command->process($data, priorFailures: 0);
        $this->assertStringContainsString('retry.1', $out);

        $out = $command->process($data, priorFailures: 1);
        $this->assertStringContainsString('retry.2', $out);
        $this->assertSame('assistant.queue.retry.2', $this->broker->publishedToQueue[1]['queue']);

        $out = $command->process($data, priorFailures: 2);
        $this->assertStringContainsString('retry.3', $out);
        $this->assertSame('assistant.queue.retry.3', $this->broker->publishedToQueue[2]['queue']);

        // The 4th attempt has exhausted all 3 tiers.
        $out = $command->process($data, priorFailures: 3);

        $this->assertStringContainsString('Dead-lettered message 42', $out);
        $dlq = collect($this->broker->publishedToQueue)->firstWhere('queue', 'assistant.queue.dlq');
        $this->assertNotNull($dlq);
        $this->assertSame($data, $dlq['data']['payload']);
        $this->assertSame(4, $dlq['data']['attempts']);
        $this->assertStringContainsString('database', $dlq['data']['error']);
    }

    public function test_a_transient_failure_recovers_on_a_later_attempt(): void
    {
        $failuresLeft = 1;
        $runner = Mockery::mock(AssistantRunner::class);
        $runner->shouldReceive('handle')->twice()->andReturnUsing(function () use (&$failuresLeft) {
            if ($failuresLeft > 0) {
                $failuresLeft--;
                throw new RuntimeException('could not reach the database');
            }
        });
        $this->app->instance(AssistantRunner::class, $runner);

        $command = app(ConsumeAssistantQueue::class);
        $data = ['message_id' => 42];

        $first = $command->process($data);
        $this->assertStringContainsString('Retrying message 42 on retry.1', $first);

        // The redelivery this simulates comes with one prior failure recorded.
        $second = $command->process($data, priorFailures: 1);
        $this->assertStringContainsString('Handled message 42', $second);

        $this->assertCount(1, $this->broker->publishedToQueue, 'a success must not publish anywhere');
    }

    // ---- a payload that will never succeed, no matter how many times it is tried ---------

    public function test_a_payload_without_a_usable_message_id_is_dead_lettered_not_silently_dropped(): void
    {
        $llm = new FakeLlmClient();
        $this->app->instance(LlmClient::class, $llm);

        app(ConsumeAssistantQueue::class)->process(['message_id' => 'abc']);

        $this->assertSame(0, $llm->calls());
        $this->assertCount(1, $this->broker->publishedToQueue);
        $this->assertSame('assistant.queue.dlq', $this->broker->publishedToQueue[0]['queue']);
        $this->assertSame(1, $this->broker->publishedToQueue[0]['data']['attempts']);
    }

    // ---- reading how many times a redelivered message already failed ---------------------

    public function test_prior_failures_reads_the_retry_attempt_header(): void
    {
        $properties = ['application_headers' => new \PhpAmqpLib\Wire\AMQPTable(['x-retry-attempt' => 2])];
        $message = new \PhpAmqpLib\Message\AMQPMessage('{}', $properties);

        $this->assertSame(2, app(ConsumeAssistantQueue::class)->priorFailures($message));
    }

    public function test_prior_failures_is_zero_on_a_first_delivery_with_no_header_at_all(): void
    {
        $message = new \PhpAmqpLib\Message\AMQPMessage('{}');

        $this->assertSame(0, app(ConsumeAssistantQueue::class)->priorFailures($message));
    }
}
