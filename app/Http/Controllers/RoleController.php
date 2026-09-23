<?php

namespace App\Http\Controllers;

use App\Contracts\Repositories\RoleInterface;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function __construct(
        private RoleInterface $roleRepository
    ) {}

    /**
     * Lookup for the user form, which needs a role_id.
     */
    public function index(): JsonResponse
    {
        return ApiResponse::success(data: $this->roleRepository->findAll());
    }
}
