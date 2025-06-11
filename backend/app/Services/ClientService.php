<?php

namespace App\Services;

use App\Models\ClientAddress;
use App\Models\User;

class ClientService
{
    public function __construct(private UserService $userService) {}

    public function createClientAddress($userId, $addressData)
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

    public function getAllClients()
    {
        $clientRoleId = $this->userService->getRoleIdByName('Client');

        return User::whereHas('roles', function ($query) use ($clientRoleId) {
            $query->where('roles.id', $clientRoleId);
        })->with('clientAddress')->get();
    }

    public function getClient($userId)
    {
        $clientRoleId = $this->userService->getRoleIdByName('Client');

        return User::where('id', $userId)
            ->whereHas('roles', function ($q) use ($clientRoleId) {
                $q->where('roles.id', $clientRoleId);
            })
            ->with('clientAddress')
            ->first();
    }

    public function deleteClientAddress($user)
    {
        $user->clientAddress()?->delete();
    }

    public function deleteClient($client)
    {
        $this->deleteClientAddress($client);

        if ($client->roles()->count() === 1) {
            $this->userService->deleteUser($client->id);
        } else {
            $clientRoleId = $this->userService->getRoleIdByName('Client');
            $client->roles()->detach($clientRoleId);
        }
    }
}
