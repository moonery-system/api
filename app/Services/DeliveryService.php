<?php

namespace App\Services;

use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\LogEventTypeEnum;
use App\Enums\NotificationTitleEnum;
use App\Factories\NotificationDescriptionFactory;
use App\Contracts\Repositories\DeliveryInterface;

class DeliveryService
{
    public function __construct(
        private UserInterface $userRepository,
        private ClientInterface $clientRepository,
        private DeliveryInterface $deliveryRepository,

        private DeliveryItemsService $deliveryItemsService,
        private NotificationService $notificationService,
        private LogService $logService,

        private NotificationDescriptionFactory $notificationDescriptionFactory
    ) {}

    public function createDelivery($deliveryValidated)
    {
        $creatorId = auth()->id();
        $client = $this->clientRepository->findById($deliveryValidated['client_id']);

        if (!$client) return false;

        $delivery = $this->deliveryRepository->create([
            'creator_id' => $creatorId,
            'client_id' => $deliveryValidated['client_id'],
            'delivery_status_id' => '1'
        ]);

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_CREATED, context: [
            'delivery' => $delivery,
        ]);

        $items = $this->deliveryItemsService->createDeliveryItems($deliveryValidated['items'], $delivery['id']);

        $this->notificationService->notifyDeliveryCreated($delivery, $items);

        return [
            'delivery' => $delivery,
            'items' => $items
        ];
    }

    public function updateDeliveryStatus($deliveryStatusValidated, $id)
    {
        $status = $this->deliveryRepository->findDeliveryStatusById($deliveryStatusValidated['status_id']);
        if (!$status) return false;

        $delivery = $this->deliveryRepository->findById($id);
        if (!$delivery || $deliveryStatusValidated['status_id'] == $delivery->delivery_status_id) return false;

        $last_status = $this->deliveryRepository->findDeliveryStatusById($delivery->delivery_status_id);

        $delivery->delivery_status_id = $status->id;
        $delivery->save();

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_STATUS_UPDATE, context: [
            'last_status' => $last_status->label,
            'new_status' => $status->label,
        ]);

        $this->notificationService->notify(
            userIds: [$delivery->client_id],
            title: NotificationTitleEnum::DELIVERY_STATUS_UPDATE_CLIENT,
            context: ['status' => $status->label]
        );

        return true;
    }

    public function generateDeliveryDescription(array $items): string
    {
        $itemNames = array_map(fn($item) => $item['name'], $items);
        return 'Delivery Items: ' . implode(', ', $itemNames) . '.';
    }

    public function deleteDelivery($id)
    {
        $delivery = $this->deliveryRepository->findById($id);
        if (!$delivery) return false;

        $delivery->delete();

        $this->logService->record(LogEventTypeEnum::DELIVERY_DELETED, [
            'delivery' => $delivery
        ]);

        return true;
    }
}
