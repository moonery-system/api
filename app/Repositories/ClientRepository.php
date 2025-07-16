<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ClientRepository implements ClientInterface
{
    public function __construct(
        private RoleInterface $roleRepository
    ){}

    public function findAll(int $perPage = 10): LengthAwarePaginator
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        return User::whereHas('roles', function ($query) use ($clientRoleId) {
                $query->where('roles.id', $clientRoleId);
            })
            ->with('clientAddress')
            ->paginate($perPage);
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

    public function findBySearch(string $search, int $perPage = 10): LengthAwarePaginator
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        $query = User::whereHas('roles', function ($q) use ($clientRoleId) {
            $q->where('roles.id', $clientRoleId);
        })
        ->with('clientAddress');

        if ($search) {
            $query->where('name', 'like', "%$search%");
        }

        return $query->paginate($perPage);
    }
}
