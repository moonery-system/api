<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The bot that answers in the chat, and what an existing database needs to have it.
 *
 * The bot is an ordinary user: no password (so it cannot log in), no activation, and a
 * role that holds no permission. Being without chat.viewAll is what keeps it out of the
 * support side of every conversation.
 *
 * Idempotent on purpose. A fresh install gets the role and the permission from the
 * other seeders; a database that already exists can run just this one:
 *
 * php artisan db:seed --class=AssistantSeeder
 */
class AssistantSeeder extends Seeder
{
    public function run()
    {
        // roles and permissions have no timestamps, so Eloquent's firstOrCreate() cannot
        // insert them (it writes created_at/updated_at): the other seeders use insert() too.
        if (!Role::where('name', 'Assistant')->exists()) Role::insert([['name' => 'Assistant']]);

        if (!Permission::where('permission', 'assistant.use')->exists()) {
            Permission::insert([['permission' => 'assistant.use']]);
        }

        $botRole = Role::where('name', 'Assistant')->firstOrFail();
        $permission = Permission::where('permission', 'assistant.use')->firstOrFail();

        // Who may talk to the assistant: the clients (and the admin, who has everything).
        foreach (['Client', 'Admin'] as $roleName) {
            Role::where('name', $roleName)->first()?->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $bot = User::firstOrCreate(
            ['email' => config('assistant.bot_email')],
            ['name' => 'Moonery Assistant']
        );

        $bot->roles()->syncWithoutDetaching([$botRole->id]);
    }
}
