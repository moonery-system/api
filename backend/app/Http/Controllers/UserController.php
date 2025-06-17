<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Repositories\UserRepository;
use App\Services\UserService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private UserRepository $userRepository
        ){}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAllUsers();
        return ApiResponse::success(data: $users);
    }

    public function store(UserStoreRequest $userRequest): JsonResponse
    {
        $userValidated = $userRequest->validated();
        
        $user = $this->userService->createUserWithInvite(
            name: $userValidated['name'],
            email: $userValidated['email'],
            roleId: $userValidated['role_id'] ?? null
        );

        return ApiResponse::success(data: $user);
    }

    public function show($id): JsonResponse
    {
        $user = $this->userRepository->findById(id: $id);
        return $user ? ApiResponse::success(data: $user) : ApiResponse::notFound();
    }

    public function update(UserUpdateRequest $request, $id): JsonResponse
    {
        $validated = $request->validated();
        $userUpdated = $this->userService->updateUser(id: $id, data: $validated);

        return $userUpdated ? ApiResponse::success(data: $userUpdated) : ApiResponse::notFound();
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $token = $request->query('token');
        $validated = $request->validated();
        $passwordChanged = $this->userService->changePassword(data: $validated, token: $token);

        return $passwordChanged ? ApiResponse::success() : ApiResponse::notFound();
    }

    public function destroy($id): JsonResponse
    {
        $userDeleted = $this->userService->deleteUser(userId: $id);

        return $userDeleted ? ApiResponse::success() : ApiResponse::notFound();;
    }
}