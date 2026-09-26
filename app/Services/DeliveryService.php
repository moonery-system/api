<?php

namespace App\Services;

use App\Contracts\Repositories\ClientAddressInterface;
use App\Contracts\Repositories\ClientInterface;
use App\Contracts\Repositories\DeliveryInterface;
use App\Contracts\Repositories\UserInterface;
use App\Enums\DeliveryStatusEnum;
use App\Enums\LogEventTypeEnum;
use App\Enums\NotificationTitleEnum;
use App\Exceptions\BusinessException;
use App\Models\Delivery;
use App\Models\DeliveryStatus;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class DeliveryService
{
    public function __construct(
        private UserInterface $userRepository,
        private ClientInterface $clientRepository,
        private DeliveryInterface $deliveryRepository,
        private ClientAddressInterface $clientAddressRepository,

        private DeliveryItemsService $deliveryItemsService,
        private NotificationService $notificationService,
        private LogService $logService,

        private DeliveryTransitionValidator $transitionValidator
    ) {}

    public function createDelivery($deliveryValidated)
    {
        $creatorId = auth()->id();
        $client = $this->clientRepository->findById($deliveryValidated['client_id']);

        if (!$client) return false;

        $address = $this->clientAddressRepository->findByIdAndUserId(
            id: $deliveryValidated['client_address_id'],
            userId: $client->id
        );

        if (!$address) return false;

        $delivery = $this->deliveryRepository->create([
            'tracking_code' => $this->generateTrackingCode(),
            'creator_id' => $creatorId,
            'client_id' => $deliveryValidated['client_id'],
            'client_address_id' => $address->id,
            'delivery_status_id' => $this->statusByName(DeliveryStatusEnum::PENDING)->id
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

    /**
     * Admin and support see everything, a delivery man sees his own plus the free
     * pool, and everyone else sees only the deliveries addressed to him.
     */
    public function listDeliveries(int $perPage, ?string $search): LengthAwarePaginator
    {
        $user = auth()->user();

        if ($user->hasPermission('deliveries.viewAll')) {
            return $this->deliveryRepository->findAllPaginated(perPage: $perPage, search: $search);
        }

        if ($user->hasPermission('deliveries.attach')) {
            return $this->deliveryRepository->findForDeliverymanPaginated(
                deliverymanId: $user->id,
                pendingStatusId: $this->statusByName(DeliveryStatusEnum::PENDING)->id,
                perPage: $perPage,
                search: $search
            );
        }

        return $this->deliveryRepository->findByClientPaginated(
            clientId: $user->id,
            perPage: $perPage,
            search: $search
        );
    }

    /**
     * Null when the delivery does not exist OR is out of the caller's scope. The
     * controller answers 404 either way, on purpose: a 403 would confirm that a
     * delivery the caller may not see does exist.
     */
    public function findVisibleDelivery(int $id): ?Delivery
    {
        $user = auth()->user();
        $delivery = $this->deliveryRepository->findById($id);

        if (!$delivery) return null;

        if ($user->hasPermission('deliveries.viewAll')) return $delivery;

        if ($user->hasPermission('deliveries.attach')) {
            $isMine = $delivery->delivery_man_id === $user->id;
            $isAvailable = is_null($delivery->delivery_man_id)
                && $delivery->status->name === DeliveryStatusEnum::PENDING->value;

            return $isMine || $isAvailable ? $delivery : null;
        }

        return $delivery->client_id === $user->id ? $delivery : null;
    }

    public function updateDeliveryStatus($deliveryStatusValidated, $id): ?Delivery
    {
        $delivery = $this->findVisibleDelivery(id: $id);
        if (!$delivery) return null;

        return $this->transitionTo(
            delivery: $delivery,
            target: DeliveryStatusEnum::from($deliveryStatusValidated['status'])
        );
    }

    public function cancelDelivery($id): ?Delivery
    {
        $delivery = $this->findVisibleDelivery(id: $id);
        if (!$delivery) return null;

        return $this->transitionTo(delivery: $delivery, target: DeliveryStatusEnum::CANCELED_BY_CLIENT);
    }

    /**
     * A delivery man takes a free delivery. The conditional update is what keeps
     * two of them from taking the same one.
     */
    public function attachDelivery($id): ?Delivery
    {
        $user = auth()->user();

        $delivery = $this->findVisibleDelivery(id: $id);
        if (!$delivery) return null;

        $affected = $this->deliveryRepository->attachDeliveryman(
            id: $delivery->id,
            deliverymanId: $user->id,
            pendingStatusId: $this->statusByName(DeliveryStatusEnum::PENDING)->id,
            attachedStatusId: $this->statusByName(DeliveryStatusEnum::ATTACHED)->id
        );

        if ($affected === 0) throw new BusinessException('This delivery is no longer available.');

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_ASSIGNED, context: [
            'delivery_id' => $delivery->id,
            'delivery_man_id' => $user->id,
        ]);

        return $this->notifyClientAndReturn(deliveryId: $delivery->id);
    }

    public function detachDelivery($id): ?Delivery
    {
        $user = auth()->user();

        $delivery = $this->findVisibleDelivery(id: $id);
        if (!$delivery) return null;

        $affected = $this->deliveryRepository->detachDeliveryman(
            id: $delivery->id,
            deliverymanId: $user->id,
            attachedStatusId: $this->statusByName(DeliveryStatusEnum::ATTACHED)->id,
            pendingStatusId: $this->statusByName(DeliveryStatusEnum::PENDING)->id
        );

        if ($affected === 0) throw new BusinessException('Only a delivery you have taken and not picked up yet can be dropped.');

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_UNASSIGNED, context: [
            'delivery_id' => $delivery->id,
            'delivery_man_id' => $user->id,
        ]);

        return $this->notifyClientAndReturn(deliveryId: $delivery->id);
    }

    /**
     * Admin assigns or reassigns. This is a change of who is responsible, not a
     * state transition, so it does not go through the transition table -- except
     * that assigning a pending delivery does move it to attached.
     */
    public function assignDeliveryman($id, $deliverymanId): ?Delivery
    {
        $delivery = $this->findVisibleDelivery(id: $id);
        if (!$delivery) return null;

        $deliveryman = $this->userRepository->findById(id: $deliverymanId);

        if (!$deliveryman || !$deliveryman->hasPermission('deliveries.attach')) {
            throw new BusinessException('This user cannot take deliveries.');
        }

        $current = $delivery->status->name;
        $assignable = [DeliveryStatusEnum::PENDING->value, DeliveryStatusEnum::ATTACHED->value];

        if (!in_array($current, $assignable)) {
            throw new BusinessException("A delivery in '{$current}' can no longer be assigned.");
        }

        $delivery->delivery_man_id = $deliveryman->id;

        if ($current === DeliveryStatusEnum::PENDING->value) {
            $delivery->delivery_status_id = $this->statusByName(DeliveryStatusEnum::ATTACHED)->id;
        }

        $delivery->save();

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_ASSIGNED, context: [
            'delivery_id' => $delivery->id,
            'delivery_man_id' => $deliveryman->id,
        ]);

        $this->notificationService->notify(
            userIds: [$deliveryman->id],
            title: NotificationTitleEnum::DELIVERY_ASSIGNED_DELIVERY_MAN,
            context: ['tracking_code' => $delivery->tracking_code]
        );

        return $this->deliveryRepository->findById($delivery->id);
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

    private function transitionTo(Delivery $delivery, DeliveryStatusEnum $target): Delivery
    {
        $user = auth()->user();

        $this->transitionValidator->assertCanTransition(delivery: $delivery, target: $target, user: $user);

        $lastStatus = $delivery->status;
        $newStatus = $this->statusByName($target);

        $delivery->delivery_status_id = $newStatus->id;

        if ($target === DeliveryStatusEnum::DELIVERED) {
            $delivery->delivered_at = Carbon::now();
        }

        $delivery->save();

        $this->logService->record(eventType: LogEventTypeEnum::DELIVERY_STATUS_UPDATE, context: [
            'delivery_id' => $delivery->id,
            'last_status' => $lastStatus->label,
            'new_status' => $newStatus->label,
        ]);

        return $this->notifyClientAndReturn(deliveryId: $delivery->id);
    }

    /**
     * The client is told what happened to his package -- unless he is the one who
     * did it.
     */
    private function notifyClientAndReturn(int $deliveryId): Delivery
    {
        $delivery = $this->deliveryRepository->findById($deliveryId);

        if ($delivery->client_id !== auth()->id()) {
            $this->notificationService->notify(
                userIds: [$delivery->client_id],
                title: NotificationTitleEnum::DELIVERY_STATUS_UPDATE_CLIENT,
                context: ['status' => $delivery->status->label]
            );
        }

        return $delivery;
    }

    private function statusByName(DeliveryStatusEnum $status): DeliveryStatus
    {
        $found = $this->deliveryRepository->findDeliveryStatusByName($status->value);

        if (!$found) throw new BusinessException("Delivery status '{$status->value}' is not seeded.");

        return $found;
    }

    private function generateTrackingCode(): string
    {
        do {
            $code = 'MNY-' . Carbon::now()->year . '-' . strtoupper(Str::random(6));
        } while ($this->deliveryRepository->existsByTrackingCode($code));

        return $code;
    }
}
