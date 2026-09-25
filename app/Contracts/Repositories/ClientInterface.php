<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface ClientInterface
{
    public function findAll(int $perPage = 10): LengthAwarePaginator;
    public function findById(int $id): ?User;
    public function findBySearch(string $search, int $perPage = 10): LengthAwarePaginator;
}
