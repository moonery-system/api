<?php

namespace App\Services;

use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use App\Models\User;

class ClientService
{
    public function __construct(
        private UserInterface $userRepository,
        private RoleInterface $roleRepository,

        private ClientInterface $clientRepository,

        private ClientAddressService $clientAddressService,
        private UserService $userService,
        private LogService $logService
    ) {}

    public function createClient($userValidated, $addressValidated): User
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        $user = $this->userService->createUserWithInvite(
            name: $userValidated['name'],
            email: $userValidated['email'],
            roleId: $clientRoleId
        );

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_CREATED, context: [
            'client' => $user,
        ]);

        $this->clientAddressService->createClientAddress(userId: $user->id, addressData: $addressValidated);

        return $user;
    }

    public function updateClient($userId, array $data)
    {
        $client = $this->userRepository->findById(id: $userId);
        if (!$client) return false;

        unset($data['email']);

        $client->update($data);

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_UPDATED, context: [
            'client' => $client,
        ]);

        return $client;
    }

    public function deleteClient($userId)
    {
        $client = $this->clientRepository->findById($userId);
        if (!$client) return false;

        $this->clientAddressService->deleteClientAddresses(user: $client);

        if ($client->roles()->count() === 1) {
            $this->userService->deleteUser(userId: $client->id);
        } else {
            $clientRoleId = $this->roleRepository->findByName('Client')->ii;
            $client->roles()->detach($clientRoleId);
            $this->logService->record(eventType: LogEventTypeEnum::CLIENT_DELETED, context: [
                'client' => $client,
            ]);
        }
    }
}
