<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserInterface
{
    public function __construct(
        private RoleInterface $roleRepository
    ) {}
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findAll(): Collection
    {
        return User::all();
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findById(int $id): ?User
    {
        return User::find($id);
    }

    public function findByRole(string $role): Collection
    {
        $roleId = $this->roleRepository->findByName($role)->id;

        return User::whereHas('roles', fn($q) => $q->where('roles.id', $roleId))->get();
    }
}
