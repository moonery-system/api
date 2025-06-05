<?php

namespace Database\Seeders;

use App\Models\User;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admin = [
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('admin'),
            'is_activated' => true
        ];

        User::insert($admin);

        $adminPermissionToUser = [
            'user_id' => 1,
            'role_id'=> 1,
        ];

        DB::table('user_roles')->insert($adminPermissionToUser);
    }
}
