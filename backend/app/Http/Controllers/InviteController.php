<?php

namespace App\Http\Controllers;

use App\Http\Requests\InviteRequest;
use App\Services\InviteService;
use App\Utils\ApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class InviteController extends Controller
{
    public function __construct(private InviteService $inviteService) {}

    public function validateToken(Request $request): JsonResponse
    {
        $token = $request->query('token');

        $tokenValidated = $this->inviteService->validateToken(token: $token);

        return $tokenValidated ? ApiResponse::success() : ApiResponse::unauthorized();
    }

    public function store(InviteRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $this->inviteService->createForEmail(email: $validated['email']);

        return ApiResponse::success();
    }
}
