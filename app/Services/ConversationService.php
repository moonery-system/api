<?php

namespace App\Services;

use App\Assistant\AssistantDispatcher;
use App\Contracts\Repositories\ConversationInterface;
use App\Contracts\Repositories\MessageInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ConversationService
{
    public const CHAT_ROUTING_KEY = 'chat.messages';

    private const SUPPORT_PERMISSION = 'chat.viewAll';

    public function __construct(
        private ConversationInterface $conversationRepository,
        private MessageInterface $messageRepository,
        private UserInterface $userRepository,

        private LogService $logService,
        private RabbitMQPublisher $publisher,
        private AssistantDispatcher $assistantDispatcher
    ) {}

    /**
     * Find-or-create, so the caller never needs to know the id of his own channel.
     */
    public function ownConversation(): Conversation
    {
        $userId = auth()->id();

        $existing = $this->conversationRepository->findByUserId($userId);

        if ($existing) return $existing;

        $this->conversationRepository->createForUser($userId);

        return $this->conversationRepository->findByUserId($userId);
    }

    /**
     * The inbox. It is support-only on purpose: a person being attended has exactly one
     * conversation, and reaches it through ownConversation() without needing to know
     * its id. The route carries can:chat.viewAll.
     */
    public function listConversations(int $perPage): LengthAwarePaginator
    {
        return $this->conversationRepository->findAllPaginated(perPage: $perPage);
    }

    /**
     * Null when it does not exist OR is out of the caller's scope. 404 either way: a
     * 403 would confirm the conversation exists.
     */
    public function findVisibleConversation(int $id): ?Conversation
    {
        $conversation = $this->conversationRepository->findById($id);

        if (!$conversation) return null;

        if ($this->isSupport()) return $conversation;

        return $conversation->user_id === auth()->id() ? $conversation : null;
    }

    public function sendMessage(int $conversationId, array $validated): ?Message
    {
        $conversation = $this->findVisibleConversation($conversationId);

        if (!$conversation) return null;

        $sender = auth()->user();

        $message = $this->storeAndPublish(
            conversation: $conversation,
            senderId: $sender->id,
            body: $validated['body'],
            deliveryId: $validated['delivery_id'] ?? null
        );

        // After the message is safely stored and published: the assistant either has
        // something to answer, or a human just took the conversation over.
        $this->assistantDispatcher->afterMessageSent(conversation: $conversation, message: $message, sender: $sender);

        return $this->messageRepository->findById($message->id);
    }

    /**
     * A message written by the assistant. There is no authenticated user on this path (it
     * runs in the consumer), so the sender is explicit and nothing here reads auth().
     * The dispatcher is deliberately not called: the bot never triggers itself.
     */
    public function sendAsAssistant(Conversation $conversation, User $bot, string $body, ?int $deliveryId = null): Message
    {
        return $this->storeAndPublish(
            conversation: $conversation,
            senderId: $bot->id,
            body: $body,
            deliveryId: $deliveryId
        );
    }

    private function storeAndPublish(Conversation $conversation, int $senderId, string $body, ?int $deliveryId): Message
    {
        $message = $this->messageRepository->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'delivery_id' => $deliveryId,
            'body' => $body,
        ]);

        // Keeps the inbox ordered by activity.
        $conversation->touch();

        $this->logService->record(eventType: LogEventTypeEnum::MESSAGE_SENT, context: [
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
        ], userId: $senderId);

        $this->publishToWebsocket(conversation: $conversation, message: $message, senderId: $senderId);

        return $message;
    }

    public function markAsRead(int $conversationId): bool
    {
        $conversation = $this->findVisibleConversation($conversationId);

        if (!$conversation) return false;

        // Reading clears what was waiting on this side of the conversation.
        $this->isSupport() && $conversation->user_id !== auth()->id()
            ? $this->messageRepository->markInboundAsRead($conversation->id)
            : $this->messageRepository->markOutboundAsRead($conversation->id);

        return true;
    }

    public function unreadCount(): int
    {
        return $this->isSupport()
            ? $this->messageRepository->countUnreadInbound()
            : $this->messageRepository->countUnreadOutboundFor(userId: auth()->id());
    }

    /**
     * Who receives is a matter of permission, so it is resolved here and shipped with
     * the message: doing it in the websocket service would mean a second copy of the
     * authorisation rules.
     */
    private function publishToWebsocket(Conversation $conversation, Message $message, int $senderId): void
    {
        $recipients = $this->userRepository
            ->findByPermission(self::SUPPORT_PERMISSION)
            ->pluck('id')
            ->push($conversation->user_id)
            ->unique()
            ->reject(fn($id) => (int) $id === (int) $senderId)
            ->values()
            ->all();

        if (!$recipients) return;

        try {
            $this->publisher->publish(self::CHAT_ROUTING_KEY, [
                'message_id' => $message->id,
                'recipient_ids' => $recipients,
            ]);
        } catch (\Throwable $e) {
            // The message is stored; a broker outage must not fail the send.
            \Illuminate\Support\Facades\Log::error('Failed to publish the chat message', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function isSupport(): bool
    {
        return auth()->user()->hasPermission(self::SUPPORT_PERMISSION);
    }
}
