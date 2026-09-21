<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $notifications = $this->notificationService->listForCurrentUser(
            perPage: (int) $request->query('per_page', 10)
        );

        return ApiResponse::paginated($notifications);
    }

    public function unreadCount(): JsonResponse
    {
        return ApiResponse::success(data: [
            'unread' => $this->notificationService->countUnreadForCurrentUser(),
        ]);
    }

    public function markAsRead($id): JsonResponse
    {
        $marked = $this->notificationService->markAsReadForCurrentUser(notificationId: $id);

        return $marked ? ApiResponse::success() : ApiResponse::notFound();
    }
}
