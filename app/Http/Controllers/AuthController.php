<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserInterface;
use App\Http\Requests\LoginRequest;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct(
        private UserInterface $userRepository
    ){}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $email = $validated['email'];
        $password = $validated['password'];

        $user = $this->userRepository->findByEmail($email);

        if (!$user->activated_at) return ApiResponse::unauthorized();

        $attempt = JWTAuth::attempt([
            'email' => $email,
            'password' => $password
        ]);

        if (!$attempt)
            return ApiResponse::unauthorized(message: 'Invalid Credentials');

        return ApiResponse::success(data: $attempt);
    }

    public function logout(Request $request): JsonResponse
    {
        auth()->logout();
        return ApiResponse::success();
    }

    public function user(): JsonResponse
    {
        $user = auth()->user();
        return ApiResponse::success(data: $user);
    }
}
