<?php

namespace App\Contracts\Repositories;

use App\Models\Delivery;
use App\Models\DeliveryStatus;
use App\Models\DeliveryStatusHistory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface DeliveryInterface
{
    public function create(array $data): Delivery;
    public function findById(int $id): ?Delivery;
    public function existsByTrackingCode(string $trackingCode): bool;
    public function createStatusHistory(array $data): DeliveryStatusHistory;

    public function findDeliveryStatusById(int $id): ?DeliveryStatus;
    public function findDeliveryStatusByName(string $name): ?DeliveryStatus;

    public function findAllPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator;
    public function findByClientPaginated(int $clientId, int $perPage = 10, ?string $search = null): LengthAwarePaginator;
    public function findForDeliverymanPaginated(int $deliverymanId, int $pendingStatusId, int $perPage = 10, ?string $search = null): LengthAwarePaginator;

    /**
     * The assistant's reads. Each one takes the client explicitly and filters by it in
     * the query, so a delivery of somebody else is not reachable from here at all.
     */
    public function findByIdForClient(int $id, int $clientId): ?Delivery;
    public function findByTrackingCodeForClient(string $trackingCode, int $clientId): ?Delivery;
    /**
     * @return Collection<int, Delivery>
     */
    public function findByClientLimited(int $clientId, ?int $statusId, int $limit): Collection;

    public function attachDeliveryman(int $id, int $deliverymanId, int $pendingStatusId, int $attachedStatusId): int;
    public function detachDeliveryman(int $id, int $deliverymanId, int $attachedStatusId, int $pendingStatusId): int;
}
