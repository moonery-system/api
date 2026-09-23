<?php

namespace App\Contracts\Repositories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;

interface RoleInterface
{
    public function findByName(string $name): ?Role;
    public function findById(int $id): ?Role;
    public function findAll(): Collection;
}
