<?php

namespace App\Http\Controllers;

use App\Services\AssistantActionService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;

class AssistantActionController extends Controller
{
    public function __construct(
        private AssistantActionService $assistantActionService
    ) {}

    public function confirm($id): JsonResponse
    {
        $delivery = $this->assistantActionService->confirm(id: (int) $id);

        return $delivery ? ApiResponse::success(data: $delivery) : ApiResponse::notFound();
    }

    public function reject($id): JsonResponse
    {
        $rejected = $this->assistantActionService->reject(id: (int) $id);

        return $rejected ? ApiResponse::success() : ApiResponse::notFound();
    }
}
