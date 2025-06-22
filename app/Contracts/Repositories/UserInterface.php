<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserInterface
{
    public function create(array $data): User;
    public function findAll(): Collection;
    public function findByEmail(string $email): ?User;
    public function findById(int $id): ?User;
    public function findByRole(string $role): Collection;
}
