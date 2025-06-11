<?php

namespace App\Services;

use App\Models\ClientAddress;

class ClientService
{
    public function createClientAddress(int $userId, array $addressData)
    {
        return ClientAddress::create([
            'user_id' => $userId,
            'address_line' => $addressData['address_line'],
            'neighborhood' => $addressData['neighborhood'],
            'city' => $addressData['city'],
            'state' => $addressData['state'],
            'zip_code' => $addressData['zip_code'],
            'complement' => $addressData['complement'] ?? null,
        ]);
    }
}