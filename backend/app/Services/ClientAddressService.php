<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\ClientAddress;
use Illuminate\Database\Eloquent\Model;

class ClientAddressService
{
    public function __construct(private LogService $logService) {}
    
    public function createClientAddress($userId, $addressData): ClientAddress|Model
    {
        $address = ClientAddress::create([
            'user_id' => $userId,
            'address_line' => $addressData['address_line'],
            'neighborhood' => $addressData['neighborhood'],
            'city' => $addressData['city'],
            'state' => $addressData['state'],
            'zip_code' => $addressData['zip_code'],
            'complement' => $addressData['complement'] ?? null,
        ]);

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_CREATED, context: [
            'address' => $address,
        ]);

        return $address;
    }

    public function deleteClientAddresses($user): void
    {
        $addresses = $user->clientAddress;

        foreach ($addresses as $address) {
            $address->delete();

            $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_DELETED, context: [
                'address' => $address,
            ]);
        }
    }

    public function deleteClientAddressById($addressId): bool
    {
        $address = ClientAddress::find(id: $addressId);

        if (!$address) {
            return false;
        }

        $address->delete();

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_DELETED, context: [
            'address' => $address,
        ]);

        return true;
    }
}
