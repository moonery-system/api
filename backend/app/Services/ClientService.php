<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\Role;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class ClientService
{
    public function __construct(
        private UserRepository $userRepository,
        private RoleRepository $roleRepository,

        private ClientAddressService $clientAddressService,
        private UserService $userService,
        private LogService $logService
    ) {}

    public function createClient($userValidated, $addressValidated): Model|User
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

    public function getAllClients(): array|Collection
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        return User::whereHas('roles', function ($query) use ($clientRoleId) {
            $query->where('roles.id', $clientRoleId);
        })->with('clientAddress')->get();
    }

    public function getClientById($userId)
    {
        $clientRoleId = $this->roleRepository->findByName('Client')->id;

        return User::where('id', $userId)
            ->whereHas('roles', function ($q) use ($clientRoleId) {
                $q->where('roles.id', $clientRoleId);
            })
            ->with('clientAddress')
            ->first();
    }

    public function deleteClient($client)
    {
        App::make(ClientAddressService::class)->deleteClientAddresses(user: $client);

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
