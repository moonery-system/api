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

    public function findById(int $id): ?ClientAddress
    {
        return ClientAddress::find($id);
    }

    public function findByIdAndUserId(int $id, int $userId): ?ClientAddress
    {
        return ClientAddress::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }
}
