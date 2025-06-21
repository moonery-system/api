<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\Delivery;
use App\Models\DeliveryItems;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;

class DeliveryService
{
    public function __construct(
        private DeliveryRepository $deliveryRepository,
        private ClientRepository $clientRepository,

        private DeliveryItemsService $deliveryItemsService,
        private LogService $logService,
    ){}

    public function createDelivery($deliveryValidated)
    {
        $creatorId = auth()->id();
        $clientId = $this->clientRepository->findById($deliveryValidated['client_id']);

        if (!$clientId) return false;

        $delivery = Delivery::create([
            'creator_id' => $creatorId,
            'client_id' => $deliveryValidated['client_id'],
            'delivery_status_id' => '1'
        ]);

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_CREATED, context: [
            'delivery' => $delivery,
        ]);

        $items = $this->deliveryItemsService->createDeliveryItems($deliveryValidated['items'], $delivery['id']);

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
        if (!$delivery) return false;

        $last_status = $this->deliveryRepository->findDeliveryStatusById($delivery->delivery_status_id);

        $delivery->delivery_status_id = $status->id;
        $delivery->save();

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_STATUS_UPDATE, context: [
            'last_status' => $delivery,
            'new_status' => $status,
        ]);

        return true;
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