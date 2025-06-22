<?php

namespace App\Services;

use App\Factories\NotificationDescriptionFactory;
use App\Repositories\NotificationRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Enums\NotificationTitleEnum;
use App\Models\Delivery;

class NotificationService
{
    public function __construct(
        private RoleRepository $roleRepository,
        private UserRepository $userRepository,
        private NotificationRepository $notificationRepository,

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
