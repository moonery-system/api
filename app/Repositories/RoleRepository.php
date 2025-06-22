<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoleInterface;
use App\Models\Role;

class RoleRepository implements RoleInterface
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
