<?php

namespace App\Repositories;

use App\Contracts\Repositories\ClientAddressInterface;
use App\Models\ClientAddress;

class ClientAddressRepository implements ClientAddressInterface
{
    public function create(array $data): ClientAddress
    {
        return ClientAddress::create($data);
    }

    public function findById(int $id): ClientAddress
    {
        return ClientAddress::find($id);
    }
}
