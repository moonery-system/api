<?php

namespace App\Http\Controllers;

use App\Http\Requests\MessageRequest;
use App\Services\ConversationService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $conversationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->conversationService->listConversations(
            perPage: (int) $request->query('per_page', 10)
        );

        return ApiResponse::paginated($conversations);
    }

    public function me(): JsonResponse
    {
        return ApiResponse::success(data: $this->conversationService->ownConversation());
    }

    public function unreadCount(): JsonResponse
    {
        return ApiResponse::success(data: [
            'unread' => $this->conversationService->unreadCount(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $conversation = $this->conversationService->findVisibleConversation(id: $id);

        return $conversation ? ApiResponse::success(data: $conversation) : ApiResponse::notFound();
    }

    public function storeMessage(MessageRequest $request, $id): JsonResponse
    {
        $message = $this->conversationService->sendMessage(
            conversationId: $id,
            validated: $request->validated()
        );

        return $message ? ApiResponse::success(data: $message) : ApiResponse::notFound();
    }

    public function markAsRead($id): JsonResponse
    {
        $marked = $this->conversationService->markAsRead(conversationId: $id);

        return $marked ? ApiResponse::success() : ApiResponse::notFound();
    }
}
