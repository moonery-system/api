<?php

namespace App\Contracts\Repositories;

use App\Models\ClientAddress;

interface ClientAddressInterface
{
    public function create(array $data): ClientAddress;
    public function findById(int $id): ClientAddress;
}
