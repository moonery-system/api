<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $support;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
        $this->fakeBroker();

        $this->client = $this->client();
        $this->support = $this->support();
    }

    private function conversationOf(User $user): Conversation
    {
        $this->actingAsUser($user)->getJson('/api/conversations/me')->assertOk();

        return Conversation::where('user_id', $user->id)->firstOrFail();
    }

    private function send(User $sender, Conversation $conversation, string $body)
    {
        return $this->actingAsUser($sender)->postJson(
            "/api/conversations/{$conversation->id}/messages",
            ['body' => $body]
        );
    }

    private function unreadFor(User $user): int
    {
        return $this->actingAsUser($user)
            ->getJson('/api/conversations/unread-count')
            ->assertOk()
            ->json('data.unread');
    }

    public function test_the_own_conversation_is_created_once_and_then_reused(): void
    {
        $first = $this->actingAsUser($this->client)->getJson('/api/conversations/me')->assertOk();
        $second = $this->actingAsUser($this->client)->getJson('/api/conversations/me')->assertOk();

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Conversation::where('user_id', $this->client->id)->count());
    }

    public function test_support_sees_the_inbox_and_a_client_does_not(): void
    {
        $this->conversationOf($this->client);
        $this->conversationOf($this->deliveryman());

        $listed = $this->actingAsUser($this->support)
            ->getJson('/api/conversations')
            ->assertOk()
            ->json('data.data');

        $this->assertCount(2, $listed);

        // No chat.viewAll, so the route refuses before the service runs.
        $this->actingAsUser($this->client)
            ->getJson('/api/conversations')
            ->assertForbidden();
    }

    public function test_a_conversation_of_another_person_answers_not_found(): void
    {
        $other = $this->conversationOf($this->client());

        $this->actingAsUser($this->client)
            ->getJson("/api/conversations/{$other->id}")
            ->assertNotFound();

        $this->send($this->client, $other, 'nao deveria entrar')->assertNotFound();
    }

    public function test_client_and_support_exchange_messages_in_the_same_conversation(): void
    {
        $conversation = $this->conversationOf($this->client);

        $this->send($this->client, $conversation, 'onde esta meu pacote?')->assertOk();
        $this->send($this->support, $conversation, 'ja saiu para entrega')->assertOk();

        $messages = $this->actingAsUser($this->client)
            ->getJson("/api/conversations/{$conversation->id}")
            ->assertOk()
            ->json('data.messages');

        $this->assertSame(
            ['onde esta meu pacote?', 'ja saiu para entrega'],
            array_column($messages, 'body')
        );
    }

    public function test_unread_follows_the_direction_of_the_message(): void
    {
        $conversation = $this->conversationOf($this->client);

        $this->send($this->client, $conversation, 'preciso de ajuda')->assertOk();

        // Waiting on support, not on the person who wrote it.
        $this->assertSame(1, $this->unreadFor($this->support));
        $this->assertSame(0, $this->unreadFor($this->client));

        $this->actingAsUser($this->support)
            ->putJson("/api/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->assertSame(0, $this->unreadFor($this->support));

        $this->send($this->support, $conversation, 'resolvido')->assertOk();

        $this->assertSame(1, $this->unreadFor($this->client));
        $this->assertSame(0, $this->unreadFor($this->support));
    }

    public function test_reading_one_side_does_not_clear_the_other(): void
    {
        $conversation = $this->conversationOf($this->client);

        $this->send($this->client, $conversation, 'pergunta')->assertOk();
        $this->send($this->support, $conversation, 'resposta')->assertOk();

        // The client reads, which clears what support wrote -- not his own message.
        $this->actingAsUser($this->client)
            ->putJson("/api/conversations/{$conversation->id}/read")
            ->assertOk();

        $this->assertSame(0, $this->unreadFor($this->client));
        $this->assertSame(1, $this->unreadFor($this->support), 'the inbound message must still be pending');
    }

    public function test_the_body_is_required(): void
    {
        $conversation = $this->conversationOf($this->client);

        $this->actingAsUser($this->client)
            ->postJson("/api/conversations/{$conversation->id}/messages", ['body' => ''])
            ->assertStatus(422);
    }
}
