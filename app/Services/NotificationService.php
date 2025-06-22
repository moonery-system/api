<?php

namespace App\Services;

use App\Contracts\Repositories\NotificationInterface;
use App\Contracts\Repositories\RoleInterface;
use App\Contracts\Repositories\UserInterface;
use App\Factories\NotificationDescriptionFactory;
use App\Enums\NotificationTitleEnum;
use App\Models\Delivery;

class NotificationService
{
    public function __construct(
        private UserInterface$userRepository,

        private RoleInterface $roleRepository,
        private NotificationInterface $notificationRepository,

        private NotificationDescriptionFactory $descriptionFactory,
    ) {}

    public function notify(array $userIds, NotificationTitleEnum $title, array $context): void
    {
        $strategy = $this->descriptionFactory->make($title);
        $description = $strategy->getDescription($context);

        $this->notificationRepository
            ->createWithUsers($title->value, $description, $userIds);
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
