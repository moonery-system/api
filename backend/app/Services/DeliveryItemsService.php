<?php

namespace App\Services;

use App\Enums\LogEventTypeEnum;
use App\Models\DeliveryItems;
use App\Repositories\ClientRepository;
use App\Repositories\DeliveryRepository;

class DeliveryItemsService
{
    public function __construct(
        private LogService $logService,
    ){}

    public function createDeliveryItems($deliveryItems, $deliveryId)
    {
        $items = [];

        foreach($deliveryItems as $item){
            $items[] = DeliveryItems::create([
                'delivery_id' => $deliveryId,
                'name' => $item['name'],
                'description' => $item['description'] ?? null,
                'quantity' => $item['quantity'],
                'weight' => $item['weight']
            ]);
        }

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_ITEMS_CREATED, context: [
            'items' => $items,
        ]);

        return $items;
    }
}