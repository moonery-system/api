<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Bulk of fake clients, useful for exercising pagination and search. Deliberately not
 * called by DatabaseSeeder: every customs:refresh-db would pay for it.
 *
 * php artisan db:seed --class=ClientsDemoSeeder
 */
class ClientsDemoSeeder extends Seeder
{
    public function run()
    {
        User::factory()->count(1000)->client()->create();
    }
}
