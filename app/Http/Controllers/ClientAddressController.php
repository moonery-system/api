<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Services\ClientAddressService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientAddressController extends Controller
{
    public function __construct(private ClientAddressService $clientAddressService) {}

    public function store(AddressRequest $request, $clientId): JsonResponse
    {
        $validated = $request->validated();

        $address = $this->clientAddressService->createClientAddress(userId: $clientId, addressData: $validated);

        return ApiResponse::success(data: $address);
    }

    public function update(AddressRequest $request, $clientId, $addressId): JsonResponse
    {
        $validated = $request->validated();

        $updated = $this->clientAddressService->updateClientAddress(
            addressId: $addressId,
            data: $validated
        );

        return $updated
            ? ApiResponse::success()
            : ApiResponse::conflict('This address is used by a delivery and cannot be edited. Register a new one.');
    }

    public function destroy($clientId, $addressId): JsonResponse
    {
        $deleted = $this->clientAddressService->deleteClientAddressById(addressId: $addressId);

        return $deleted ? ApiResponse::success() : ApiResponse::notFound();
    }
}
