<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RelatesPermissionsToRoles extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
            $adminRole = Role::where('name', 'Admin')->first();
            $permissions = Permission::pluck('id');
            $adminRole->permissions()->sync($permissions);
    
            $clientRole = Role::where('name', 'Client')->first();
            $clientPermissions = Permission::whereIn('permission', [
                'clients.view',
                'deliveries.view'
            ])->pluck('id');
            $clientRole->permissions()->sync($clientPermissions);
    
            $deliveryRole = Role::where('name', 'Delivery Man')->first();
            $deliveryPermissions = Permission::whereIn('permission', [
                'deliveries.view',
                'deliveries.viewAny',
                'delivery.update'
            ])->pluck('id');
            $deliveryRole->permissions()->sync($deliveryPermissions);
    }
}
