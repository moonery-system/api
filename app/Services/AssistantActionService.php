<?php

namespace App\Services;

use App\Contracts\Repositories\AssistantPendingActionInterface;
use App\Contracts\Repositories\ConversationInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use App\Exceptions\BusinessException;
use App\Models\AssistantPendingAction;
use App\Models\Delivery;

/**
 * The customer's answer to "do you confirm?". This is the only door through which the
 * assistant's cancellation happens, and it is a click, not a sentence in the chat.
 *
 * The cancellation itself is DeliveryService::cancelDelivery(): the same code, and so the
 * same state machine, permission and ownership rules, as when the customer cancels by hand.
 */
class AssistantActionService
{
    public function __construct(
        private AssistantPendingActionInterface $pendingActions,
        private ConversationInterface $conversationRepository,
        private UserInterface $userRepository,

        private DeliveryService $deliveryService,
        private ConversationService $conversationService,
        private LogService $logService,
    ) {}

    /**
     * Null when the confirmation does not exist or is not the caller's (404 either way).
     *
     * @throws BusinessException when it is not pending any more, has expired, or the
     *         delivery can no longer be canceled
     */
    public function confirm(int $id): ?Delivery
    {
        $user = auth()->user();

        $action = $this->pendingActions->findByIdForUser($id, $user->id);

        if (!$action) return null;

        $this->assertPending($action);

        // The conditional update is what lets only one click win, and only before expiry.
        if ($this->pendingActions->claimPending($id, $user->id) === 0) {
            // Either it expired, or another click got there first. Only the first case is
            // recorded, and only if it is still pending: the winner's answer is never touched.
            $this->pendingActions->expireIfDue($id);

            throw new BusinessException('This confirmation is no longer available: it expired or was already answered.');
        }

        try {
            $delivery = $this->deliveryService->cancelDelivery($action->delivery_id);

            if (!$delivery) throw new BusinessException('This delivery is no longer available.');
        } catch (BusinessException $e) {
            $this->pendingActions->resolve($id, AssistantPendingAction::STATUS_FAILED, $e->getMessage());

            $this->say($action, 'cancel_failed', $this->trackingCodeOf($action));

            throw $e;
        }

        $this->logService->record(eventType: LogEventTypeEnum::ASSISTANT_CANCEL_CONFIRMED, context: [
            'pending_action_id' => $action->id,
            'delivery_id' => $delivery->id,
        ]);

        $this->say($action, 'canceled', $delivery->tracking_code);

        return $delivery;
    }

    /**
     * @throws BusinessException when it is not pending any more
     */
    public function reject(int $id): bool
    {
        $user = auth()->user();

        $action = $this->pendingActions->findByIdForUser($id, $user->id);

        if (!$action) return false;

        $this->assertPending($action);

        $this->pendingActions->resolve($id, AssistantPendingAction::STATUS_REJECTED);

        $this->logService->record(eventType: LogEventTypeEnum::ASSISTANT_CANCEL_REJECTED, context: [
            'pending_action_id' => $action->id,
            'delivery_id' => $action->delivery_id,
        ]);

        $this->say($action, 'kept', $this->trackingCodeOf($action));

        return true;
    }

    private function assertPending(AssistantPendingAction $action): void
    {
        if ($action->status !== AssistantPendingAction::STATUS_PENDING) {
            throw new BusinessException("This confirmation was already resolved ({$action->status}).");
        }
    }

    /**
     * The assistant closes the loop in the chat, so the conversation reads as one story.
     * Missing conversation or bot is not worth failing the cancellation over.
     */
    private function say(AssistantPendingAction $action, string $messageKey, string $trackingCode): void
    {
        $conversation = $this->conversationRepository->findById($action->conversation_id);
        $bot = $this->userRepository->findByEmail((string) config('assistant.bot_email'));

        if (!$conversation || !$bot) return;

        $this->conversationService->sendAsAssistant(
            conversation: $conversation,
            bot: $bot,
            body: str_replace(':tracking_code', $trackingCode, (string) config("assistant.messages.{$messageKey}")),
            deliveryId: $action->delivery_id,
        );
    }

    private function trackingCodeOf(AssistantPendingAction $action): string
    {
        return (string) $action->delivery()->value('tracking_code');
    }
}
