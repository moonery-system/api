<?php

namespace App\Http\Controllers;

use App\Utils\ApiResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ], [
            'email.required' => 'The email is required.',
            'email.email' => 'The email must be a valid email.',
            'password.required' => 'The password is required.'
        ]);

        $email = $request->email;
        $password = $request->password;

        $attempt = auth()->attempt([
            'email' => $email,
            'password' => $password
        ]);

        if (!$attempt)
            return ApiResponse::unauthorized('Invalid Credentials');

        $user = auth()->user();
        $token = $user->createToken(
            $user->name,
            ['*'],
            now()->addWeek()
        )->plainTextToken;

        return ApiResponse::success($token);
    }
}
