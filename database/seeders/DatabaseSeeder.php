<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            PermissionsSeeder::class,
            RolesSeeder::class,
            RelatesPermissionsToRoles::class,
            UsersSeeder::class,
            RelatesRolesToUsers::class,
            DeliveryStatusSeeder::class,
        ]);

        User::factory()->count(1000)->client()->create();
    }
}
