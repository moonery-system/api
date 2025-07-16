<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\ClientInterface;
use App\Http\Requests\AddressRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Services\ClientService;
use App\Services\UserService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private ClientInterface $clientRepository,

        private UserService $userService,
        private ClientService $clientService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');
        $perPage = (int) $request->query('per_page', 10);

        $clients = $search
            ? $this->clientRepository->findBySearch($search, $perPage)
            : $this->clientRepository->findAll($perPage);

        return ApiResponse::paginated($clients);
    }

    public function store(UserStoreRequest $userRequest, AddressRequest $addressRequest): JsonResponse
    {
        $userValidated = $userRequest->validated();
        $addressValidated = $addressRequest->validated();

        $user = $this->clientService->createClient(userValidated: $userValidated, addressValidated: $addressValidated);

        return ApiResponse::success(data: $user);
    }

    public function show($id): JsonResponse
    {
        $client = $this->clientRepository->findById(id: $id);

        return $client ? ApiResponse::success(data: $client) : ApiResponse::notFound();
    }

    public function update(UserUpdateRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();
        $clientUpdated = $this->clientService->updateClient(userId: $id, data: $validated);

        return $clientUpdated ? ApiResponse::success(data: $clientUpdated) : ApiResponse::notFound();
    }

    public function destroy($id): JsonResponse
    {
        $client = $this->clientService->deleteClient(userId: $id);

        return $client ? ApiResponse::success() : ApiResponse::notFound();
    }
}
