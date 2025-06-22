<?php

namespace App\Contracts\Repositories;

use App\Models\Delivery;
use App\Models\DeliveryStatus;
use Illuminate\Database\Eloquent\Collection;

interface DeliveryInterface
{
    public function create(array $data): Delivery;
    public function findAll(): Collection;
    public function findById(int $id): ?Delivery;
    public function findDeliveryStatusById(int $id): ?DeliveryStatus;
}
