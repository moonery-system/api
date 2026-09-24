<?php

namespace Database\Seeders;

use App\Models\Role;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = [
            'Admin',
            'Client',
            'Delivery Man',
            'Support',
            // The chat bot. It holds no permission at all: not even chat.viewAll.
            'Assistant',
        ];

        Role::insert(
            collect($roles)->map(fn($p) => ['name' => $p])->toArray()
        );
    }
}
