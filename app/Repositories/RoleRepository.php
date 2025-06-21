<?php

namespace App\Repositories;

use App\Models\Role;

class RoleRepository
{
    public function findByName(string $name): ?Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    public function findById(int $id): ?Role
    {
        return Role::find($id);
    }
}
