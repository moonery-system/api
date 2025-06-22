<?php

namespace App\Contracts\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface ClientInterface
{
    public function findAll(): Collection;
    public function findById(int $id): ?User;
}
