<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = [
            [
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('admin'),
                'activated_at' => Carbon::now(),
            ],
            [
                'name' => 'client',
                'email' => 'client@gmail.com',
                'password' => bcrypt('client'),
                'activated_at' => Carbon::now(),
            ],
            [
                'name' => 'deliveryman',
                'email' => 'deliveryman@gmail.com',
                'password' => bcrypt('deliveryman'),
                'activated_at' => Carbon::now(),
            ]
        ];

        foreach ($users as $user) {
            User::create($user);
        }
    }
}
