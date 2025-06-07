<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RelatesRolesToUsers extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $adminUser = User::where('name', 'admin')->first();
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminUser && $adminRole) {
            $adminUser->roles()->sync([$adminRole->id]);
        }

        $clientUser = User::where('name', 'client')->first();
        $clientRole = Role::where('name', 'Client')->first();
        if ($clientUser && $clientRole) {
            $clientUser->roles()->sync([$clientRole->id]);
        }

        $deliveryUser = User::where('name', 'deliveryman')->first();
        $deliveryRole = Role::where('name', 'Delivery Man')->first();
        if ($deliveryUser && $deliveryRole) {
            $deliveryUser->roles()->sync([$deliveryRole->id]);
        }
    }
}
