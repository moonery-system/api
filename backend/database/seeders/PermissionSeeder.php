<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            [
                'permission' => 'users.create'
            ],
            [
                'permission' => 'users.update'
            ],
            [
                'permission' => 'users.delete'
            ],
            [
                'permission' => 'users.view'
            ],
            [
                'permission' => 'users.viewAny'
            ]
        ];

        Permission::insert($permissions);
    }
}
