<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageAssistantFlagTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $support;
    private User $bot;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

        $this->client = $this->client();
        $this->support = $this->support();
        $this->bot = User::where('email', config('assistant.bot_email'))->firstOrFail();
        $this->conversation = Conversation::create(['user_id' => $this->client->id]);

        foreach ([[$this->client, 'from the client'], [$this->bot, 'from the bot'], [$this->support, 'from support']] as [$sender, $body]) {
            Message::create(['conversation_id' => $this->conversation->id, 'sender_id' => $sender->id, 'body' => $body]);
        }
    }

    private function flagsByBody(array $messages): array
    {
        return collect($messages)->pluck('is_assistant', 'body')->all();
    }

    public function test_only_the_message_of_the_bot_is_flagged_for_the_customer(): void
    {
        $messages = $this->actingAsUser($this->client)->getJson('/api/conversations/me')->assertOk()->json('data.messages');

        $this->assertSame(
            ['from the client' => false, 'from the bot' => true, 'from support' => false],
            $this->flagsByBody($messages)
        );
    }

    public function test_the_support_sees_the_same_flags(): void
    {
        $messages = $this->actingAsUser($this->support)
            ->getJson("/api/conversations/{$this->conversation->id}")->assertOk()->json('data.messages');

        $this->assertSame(
            ['from the client' => false, 'from the bot' => true, 'from support' => false],
            $this->flagsByBody($messages)
        );
    }

    public function test_a_message_just_sent_carries_the_flag_too(): void
    {
        $this->actingAsUser($this->client)
            ->postJson("/api/conversations/{$this->conversation->id}/messages", ['body' => 'hello'])
            ->assertOk()
            ->assertJsonPath('data.is_assistant', false);
    }

    public function test_nothing_is_flagged_when_the_bot_does_not_exist(): void
    {
        $this->bot->roles()->detach();
        Message::where('sender_id', $this->bot->id)->delete();
        $this->bot->forceDelete();

        $messages = $this->actingAsUser($this->client)->getJson('/api/conversations/me')->assertOk()->json('data.messages');

        $this->assertNotContains(true, array_column($messages, 'is_assistant'));
    }
}
