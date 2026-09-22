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
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $email = $validated['email'];
        $password = $validated['password'];

        $user = $this->userRepository->findByEmail($email);

        if (!$user || !$user->activated_at) return ApiResponse::unauthorized("Invalid Credentials");

        $attempt = JWTAuth::attempt([
            'email' => $email,
            'password' => $password
        ]);

        if (!$attempt)
            return ApiResponse::unauthorized(message: 'Invalid Credentials');

        return ApiResponse::success(message: 'Login successful')
            ->withCookie($this->tokenCookie($attempt));
    }

    /**
     * Hands back a fresh token so a session does not die at the 60 minute TTL. The
     * old one goes to the blacklist (enabled in config/jwt.php).
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = auth()->refresh();
        } catch (\Throwable $e) {
            return ApiResponse::unauthorized(message: 'Token cannot be refreshed');
        }

        return ApiResponse::success(message: 'Token refreshed')
            ->withCookie($this->tokenCookie($token));
    }

    private function tokenCookie(string $token)
    {
        return cookie(
            'token',
            $token,
            60,
            null,
            null,
            true,
            true,
            false,
            'Strict'
        );
    }

    public function logout(Request $request): JsonResponse
    {
        auth()->logout();
        $cookie = cookie('token', '', -1, '/', null, true, true);

        return ApiResponse::success()
            ->withCookie($cookie);
    }

    public function user(): JsonResponse
    {
        $user = auth()->user();
        $permissions = $user->permissions()->pluck('permission');

        $data = [
            'user' => $user,
            'permissions' => $permissions
        ];

        return ApiResponse::success(data: $data);
    }
}
