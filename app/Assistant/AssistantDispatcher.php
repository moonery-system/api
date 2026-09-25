<?php

namespace App\Assistant;

use App\Contracts\Repositories\ConversationInterface;
use App\Enums\LogEventTypeEnum;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\LogService;
use App\Services\RabbitMQPublisher;
use Illuminate\Support\Facades\Log;

/**
 * What happens to the assistant when a message is sent: either it is asked to answer, or a
 * human took the conversation over and it stops.
 *
 * It only queues the work. Answering takes seconds (a provider call, with spacing between
 * calls), which must not sit on the request that posts the message.
 */
class AssistantDispatcher
{
    public const ROUTING_KEY = 'assistant.requests';

    private const SUPPORT_PERMISSION = 'chat.viewAll';
    private const USE_PERMISSION = 'assistant.use';

    public function __construct(
        private ConversationInterface $conversationRepository,
        private RabbitMQPublisher $publisher,
        private LogService $logService,
    ) {}

    public function afterMessageSent(Conversation $conversation, Message $message, User $sender): void
    {
        if (!config('assistant.enabled')) return;

        if ($this->isSupportReplying($conversation, $sender)) {
            $this->silence($conversation, $sender);

            return;
        }

        if ($this->shouldAnswer($conversation, $sender)) {
            $this->queue($message);
        }
    }

    /**
     * Who may set the assistant off: the owner of the conversation, who holds the
     * permission to use it and is not on the support side. Decided by permission, never
     * by the name of a role.
     */
    private function shouldAnswer(Conversation $conversation, User $sender): bool
    {
        return $conversation->assistant_status === Conversation::ASSISTANT_ACTIVE
            && $sender->id === $conversation->user_id
            && $sender->hasPermission(self::USE_PERMISSION)
            && !$sender->hasPermission(self::SUPPORT_PERMISSION);
    }

    private function isSupportReplying(Conversation $conversation, User $sender): bool
    {
        return $sender->id !== $conversation->user_id && $sender->hasPermission(self::SUPPORT_PERMISSION);
    }

    /**
     * A human answered: from now on the conversation is theirs.
     */
    private function silence(Conversation $conversation, User $support): void
    {
        $flipped = $this->conversationRepository->markHandedOff($conversation->id, 'support_replied');

        if ($flipped === 0) return;

        $this->logService->record(eventType: LogEventTypeEnum::ASSISTANT_HANDOFF, context: [
            'conversation_id' => $conversation->id,
            'reason' => 'support_replied',
        ], userId: $support->id);
    }

    private function queue(Message $message): void
    {
        try {
            $this->publisher->publish(self::ROUTING_KEY, ['message_id' => $message->id]);
        } catch (\Throwable $e) {
            // The message is stored and the customer can still reach support: a broker
            // outage must not fail the send.
            Log::error('Failed to queue the assistant request', [
                'message_id' => $message->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
