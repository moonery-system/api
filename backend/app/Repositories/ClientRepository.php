<?php

namespace App\Repositories;

use App\Models\User;

class ClientRepository
{
    public function __construct(
        private RoleRepository $roleRepository
    ){}

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
