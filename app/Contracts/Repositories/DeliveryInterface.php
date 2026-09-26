<?php

namespace App\Contracts\Repositories;

use App\Models\Delivery;
use App\Models\DeliveryStatus;
use Illuminate\Pagination\LengthAwarePaginator;

interface DeliveryInterface
{
    public function create(array $data): Delivery;
    public function findById(int $id): ?Delivery;
    public function existsByTrackingCode(string $trackingCode): bool;

    public function findDeliveryStatusById(int $id): ?DeliveryStatus;
    public function findDeliveryStatusByName(string $name): ?DeliveryStatus;

    public function findAllPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator;
    public function findByClientPaginated(int $clientId, int $perPage = 10, ?string $search = null): LengthAwarePaginator;
    public function findForDeliverymanPaginated(int $deliverymanId, int $pendingStatusId, int $perPage = 10, ?string $search = null): LengthAwarePaginator;

    public function attachDeliveryman(int $id, int $deliverymanId, int $pendingStatusId, int $attachedStatusId): int;
    public function detachDeliveryman(int $id, int $deliverymanId, int $attachedStatusId, int $pendingStatusId): int;
}
