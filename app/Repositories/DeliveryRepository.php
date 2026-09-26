<?php

namespace App\Repositories;

use App\Contracts\Repositories\DeliveryInterface;
use App\Models\Delivery;
use App\Models\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class DeliveryRepository implements DeliveryInterface
{
    public function create(array $data): Delivery
    {
        return Delivery::create($data);
    }

    public function findById(int $id): ?Delivery
    {
        return Delivery::with(['items', 'status', 'address', 'client'])->find($id);
    }

    public function existsByTrackingCode(string $trackingCode): bool
    {
        return Delivery::where('tracking_code', $trackingCode)->exists();
    }

    public function findDeliveryStatusById(int $id): ?DeliveryStatus
    {
        return DeliveryStatus::find($id);
    }

    public function findDeliveryStatusByName(string $name): ?DeliveryStatus
    {
        return DeliveryStatus::where('name', $name)->first();
    }

    public function findAllPaginated(int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($search)->paginate($perPage);
    }

    public function findByClientPaginated(int $clientId, int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($search)
            ->where('client_id', $clientId)
            ->paginate($perPage);
    }

    public function findForDeliverymanPaginated(int $deliverymanId, int $pendingStatusId, int $perPage = 10, ?string $search = null): LengthAwarePaginator
    {
        return $this->baseQuery($search)
            ->where(function (Builder $query) use ($deliverymanId, $pendingStatusId) {
                $query->where('delivery_man_id', $deliverymanId)
                    ->orWhere(function (Builder $available) use ($pendingStatusId) {
                        $available->whereNull('delivery_man_id')
                            ->where('delivery_status_id', $pendingStatusId);
                    });
            })
            ->paginate($perPage);
    }

    public function attachDeliveryman(int $id, int $deliverymanId, int $pendingStatusId, int $attachedStatusId): int
    {
        return Delivery::where('id', $id)
            ->whereNull('delivery_man_id')
            ->where('delivery_status_id', $pendingStatusId)
            ->update([
                'delivery_man_id' => $deliverymanId,
                'delivery_status_id' => $attachedStatusId,
            ]);
    }

    public function detachDeliveryman(int $id, int $deliverymanId, int $attachedStatusId, int $pendingStatusId): int
    {
        return Delivery::where('id', $id)
            ->where('delivery_man_id', $deliverymanId)
            ->where('delivery_status_id', $attachedStatusId)
            ->update([
                'delivery_man_id' => null,
                'delivery_status_id' => $pendingStatusId,
            ]);
    }

    private function baseQuery(?string $search): Builder
    {
        $query = Delivery::with(['items', 'status', 'address', 'client'])->latest();

        if ($search) {
            $query->where(function (Builder $filter) use ($search) {
                $filter->where('tracking_code', 'like', "%$search%")
                    ->orWhereHas('client', fn(Builder $client) => $client->where('name', 'like', "%$search%"));
            });
        }

        return $query;
    }
}
