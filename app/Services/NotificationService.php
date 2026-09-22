<?php

namespace App\Services;

use App\Contracts\Repositories\NotificationInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Factories\NotificationDescriptionFactory;
use App\Enums\NotificationTitleEnum;
use App\Models\Delivery;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

class NotificationService
{
    public function __construct(
        private UserInterface $userRepository,
        private RoleInterface $roleRepository,
        private NotificationInterface $notificationRepository,

        private NotificationDescriptionFactory $descriptionFactory,

        private RabbitMQPublisher $publisher,
    ) {}

    /**
     * Notification is the user's own resource: the scope comes from the authenticated
     * id, not from a permission.
     */
    public function listForCurrentUser(int $perPage): LengthAwarePaginator
    {
        return $this->notificationRepository->findByUserPaginated(
            userId: auth()->id(),
            perPage: $perPage
        );
    }

    public function countUnreadForCurrentUser(): int
    {
        return $this->notificationRepository->countUnreadByUser(userId: auth()->id());
    }

    public function markAsReadForCurrentUser($notificationId): bool
    {
        return $this->notificationRepository->markAsRead(
            userId: auth()->id(),
            notificationId: (int) $notificationId
        );
    }

    public function notify(array $userIds, NotificationTitleEnum $title, array $context): void
    {
        $strategy = $this->descriptionFactory->make($title);
        $description = $strategy->getDescription($context);

        $notification = $this->notificationRepository->createWithUsers($title->value, $description, $userIds);

        // A notificacao ja esta no banco. Broker fora nao pode derrubar a acao de
        // dominio que a gerou -- o usuario ainda a encontra na listagem.
        try {
            $this->publisher->publish('notifications.email', [
                'notification_id' => $notification->id,
            ]);

            $this->publisher->publish('notifications.websocket', [
                'notification_id' => $notification->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to publish the notification', [
                'notification_id' => $notification->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function notifyDeliveryCreated(Delivery $delivery, array $items)
    {
        $this->notify(
            userIds: [$delivery->client_id],
            title: NotificationTitleEnum::DELIVERY_CREATED_CLIENT,
            context: ['items' => $items]
        );

        $deliverymanIds = $this->userRepository->findByRole('Delivery Man')->pluck('id')->toArray();

        $this->notify(
            userIds: $deliverymanIds,
            title: NotificationTitleEnum::DELIVERY_CREATED_DELIVERY_MAN,
            context: ['items' => $items]
        );
    }
}
