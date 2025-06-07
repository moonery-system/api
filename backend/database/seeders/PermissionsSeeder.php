<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $permissions = [
            'users.create',
            'users.update',
            'users.delete',
            'users.view',
            'users.viewAny',
            'clients.create',
            'clients.update',
            'clients.delete',
            'clients.view',
            'clients.viewAny',
            'deliveries.create',
            'deliveries.update',
            'deliveries.delete',
            'deliveries.view',
            'deliveries.viewAny'
        ];

        Permission::insert(
            collect($permissions)->map(fn($p) => ['permission' => $p])->toArray()
        );
    }
}
