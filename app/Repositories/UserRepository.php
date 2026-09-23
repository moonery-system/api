<?php

namespace App\Repositories;

use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /**
     * One shape for the listing, filtered or not. Molded on
     * ClientRepository::findBySearch, which is the pagination pattern of the project.
     */
    public function findAllPaginated(int $perPage = 10, ?string $search = null, ?string $role = null): LengthAwarePaginator
    {
        $query = User::with('roles');

        if ($search) {
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%");
            });
        }

        if ($role) {
            $query->whereHas('roles', fn(Builder $q) => $q->where('roles.name', $role));
        }

        return $query->latest()->paginate($perPage);
    }

    public function findByRole(string $role): Collection
    {
        $roleId = $this->roleRepository->findByName($role)->id;

        return User::whereHas('roles', fn($q) => $q->where('roles.id', $roleId))->get();
    }
}
