<?php

namespace Tests\Feature;

use App\Assistant\Llm\LlmClient;
use App\Console\Commands\ConsumeAssistantQueue;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeLlmClient;
use Tests\TestCase;

class ConsumeAssistantQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

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
}
