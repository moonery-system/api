<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AssistantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantBotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDomain();
    }

    private function bot(): User
    {
        return User::where('email', config('assistant.bot_email'))->firstOrFail();
    }

    public function test_the_bot_exists_and_holds_no_permission(): void
    {
        $bot = $this->bot();

        $this->assertCount(0, $bot->permissions());
        $this->assertFalse($bot->hasPermission('chat.viewAll'));
        $this->assertFalse($bot->hasPermission('assistant.use'));
    }

    public function test_the_bot_is_never_on_the_support_side(): void
    {
        $support = app(\App\Contracts\Repositories\UserInterface::class)->findByPermission('chat.viewAll');

        $this->assertFalse($support->contains('id', $this->bot()->id));
    }

    public function test_the_bot_cannot_log_in(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => config('assistant.bot_email'),
            'password' => '',
        ])->assertStatus(422);

        $this->postJson('/api/auth/login', [
            'email' => config('assistant.bot_email'),
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_only_the_client_and_the_admin_may_use_the_assistant(): void
    {
        $this->assertTrue($this->client()->hasPermission('assistant.use'));
        $this->assertTrue($this->admin()->hasPermission('assistant.use'));

        $this->assertFalse($this->support()->hasPermission('assistant.use'));
        $this->assertFalse($this->deliveryman()->hasPermission('assistant.use'));
    }

    public function test_the_seeder_can_run_again_without_duplicating_anything(): void
    {
        $this->seed(AssistantSeeder::class);
        $this->seed(AssistantSeeder::class);

        $this->assertSame(1, User::where('email', config('assistant.bot_email'))->count());
    }
}
