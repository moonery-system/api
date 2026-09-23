<?php

namespace App\Services;

use App\Contracts\Repositories\ClientAddressInterface;
use App\Enums\LogEventTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\ClientAddress;

class ClientAddressService
{
    public function __construct(
        private ClientAddressInterface $clientAddressRepository,

        private LogService $logService
    ) {}
    
    public function createClientAddress($userId, $addressData): ClientAddress
    {
        $address = $this->clientAddressRepository->create([
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

    /**
     * An address referenced by a delivery is history: editing it would rewrite where a
     * past delivery went. In that case the client registers a new address instead.
     */
    public function updateClientAddress($addressId, array $data): bool
    {
        $address = $this->clientAddressRepository->findById(id: $addressId);

        if (!$address || $address->deliveries()->exists()) return false;

        $address->update($data);

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_UPDATED, context: [
            'address' => $address,
        ]);

        return true;
    }

    public function deleteClientAddresses($user): void
    {
        $addresses = $user->clientAddress;

        foreach ($addresses as $address) {
            if ($address->deliveries()->exists()) continue;

            $address->delete();

            $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_DELETED, context: [
                'address' => $address,
            ]);
        }
    }

    public function deleteClientAddressById($addressId): bool
    {
        $address = $this->clientAddressRepository->findById(id: $addressId);

        if (!$address) return false;

        // Mesma regra do update: endereco referenciado por entrega e historico.
        // 409 com mensagem, e nao 404, para o chamador saber por que falhou.
        if ($address->deliveries()->exists()) {
            throw new BusinessException('This address is used by a delivery and cannot be deleted.');
        }

        $address->delete();

        $this->logService->record(eventType: LogEventTypeEnum::CLIENT_ADDRESS_DELETED, context: [
            'address' => $address,
        ]);

        return true;
    }
}
