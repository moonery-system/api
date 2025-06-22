<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClientRepository implements ClientInterface
{
    public function __construct(
        private RoleInterface $roleRepository
    ){}

    public function findAll(): Collection
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        return User::whereHas('roles', function ($query) use ($clientRoleId) {
            $query->where('roles.id', $clientRoleId);
        })->with('clientAddress')->get();
    }

    public function findById(int $id): ?User
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        return User::where('id', $id)
            ->whereHas('roles', function ($q) use ($clientRoleId) {
                $q->where('roles.id', $clientRoleId);
            })
            ->with('clientAddress')
            ->first();
    }
}
