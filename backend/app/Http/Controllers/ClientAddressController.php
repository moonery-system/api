<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddressRequest;
use App\Services\ClientAddressService;
use App\Services\ClientService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientAddressController extends Controller
{
    public function __construct(private ClientService $clientService, private ClientAddressService $clientAddressService) {}

    public function store(AddressRequest $request, $clientId): JsonResponse
    {
        $validated = $request->validated();

        $address = $this->clientAddressService->createClientAddress(userId: $clientId, addressData: $validated);

        return ApiResponse::success(data: $address);
    }

    public function update(AddressRequest $request, $clientId, $addressId): void
    {
        // update logic
    }

    public function destroy($clientId, $addressId): JsonResponse
    {
        $deleted = $this->clientAddressService->deleteClientAddressById(addressId: $addressId);

        return $deleted ? ApiResponse::success() : ApiResponse::notFound();
    }
}
