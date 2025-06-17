<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(rules: [
            'email' => 'required|email',
            'password' => 'required'
        ], params: [
            'email.required' => 'The email is required.',
            'email.email' => 'The email must be a valid email.',
            'password.required' => 'The password is required.'
        ]);

        $email = $request->email;
        $password = $request->password;

        $user = User::where('email', $email)->first();

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
