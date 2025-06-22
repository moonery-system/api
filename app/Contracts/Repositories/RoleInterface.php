<?php

namespace App\Contracts\Repositories;

use App\Models\Role;

interface RoleInterface
{
    public function findByName(string $name): ?Role;
    public function findById(int $id): ?Role;
}
