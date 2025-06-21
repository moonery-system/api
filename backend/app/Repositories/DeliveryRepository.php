<?php

namespace App\Repositories;

use App\Models\Delivery;
use App\Models\DeliveryStatus;
use Illuminate\Database\Eloquent\Collection;

class DeliveryRepository
{
    public function findAll(): Collection
    {
        return Delivery::all();
    }

    public function findById(int $id): ?Delivery
    {
        return Delivery::with('items')->get()->find($id);
    }

    public function findDeliveryStatusById(int $id): ?DeliveryStatus
    {
        return DeliveryStatus::find($id);
    }
}