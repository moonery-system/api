<?php

namespace App\Services;

use App\Enums\DeliveryStatusEnum;
use App\Exceptions\BusinessException;
use App\Models\Delivery;
use App\Models\User;

class DeliveryTransitionValidator
{
    public const ACTOR_ANY = 'any';
    public const ACTOR_OWNER_CLIENT = 'owner_client';
    public const ACTOR_OWNER_DELIVERYMAN = 'owner_deliveryman';

    /**
     * Source status => target status => [required permission, required actor].
     *
     * pending <-> attached is deliberately absent: taking and dropping a delivery
     * go through attach/detach, which do it with a conditional update so two
     * deliverymen cannot take the same delivery.
     *
     * @return array<string, array<string, array{0: string, 1: string}>>
     */
    private static function transitions(): array
    {
        return [
            DeliveryStatusEnum::PENDING->value => [
                DeliveryStatusEnum::CANCELED_BY_CLIENT->value => ['deliveries.cancel', self::ACTOR_OWNER_CLIENT],
                DeliveryStatusEnum::CANCELED_BY_ADMIN->value => ['deliveries.cancelAny', self::ACTOR_ANY],
            ],

            DeliveryStatusEnum::ATTACHED->value => [
                DeliveryStatusEnum::PICKED_UP->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::CANCELED_BY_CLIENT->value => ['deliveries.cancel', self::ACTOR_OWNER_CLIENT],
                DeliveryStatusEnum::CANCELED_BY_ADMIN->value => ['deliveries.cancelAny', self::ACTOR_ANY],
            ],

            DeliveryStatusEnum::PICKED_UP->value => [
                DeliveryStatusEnum::IN_TRANSIT->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::CANCELED_BY_ADMIN->value => ['deliveries.cancelAny', self::ACTOR_ANY],
            ],

            DeliveryStatusEnum::IN_TRANSIT->value => [
                DeliveryStatusEnum::DELIVERED->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::CLIENT_ADDRESS_NOT_FOUND->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::CLIENT_NOT_FOUND->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::CANCELED_BY_ADMIN->value => ['deliveries.cancelAny', self::ACTOR_ANY],
            ],

            DeliveryStatusEnum::CLIENT_ADDRESS_NOT_FOUND->value => [
                DeliveryStatusEnum::IN_TRANSIT->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::RETURN_TO_SENDER->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
            ],

            DeliveryStatusEnum::CLIENT_NOT_FOUND->value => [
                DeliveryStatusEnum::IN_TRANSIT->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
                DeliveryStatusEnum::RETURN_TO_SENDER->value => ['deliveries.update', self::ACTOR_OWNER_DELIVERYMAN],
            ],

            // delivered, canceled_by_client, canceled_by_admin and return_to_sender
            // are final: no transition leaves them.
        ];
    }

    public function assertCanTransition(Delivery $delivery, DeliveryStatusEnum $target, User $user): void
    {
        $current = $delivery->status->name;

        $allowed = self::transitions()[$current] ?? [];

        if (!isset($allowed[$target->value])) {
            throw new BusinessException("A delivery in '{$current}' cannot move to '{$target->value}'.");
        }

        [$permission, $actor] = $allowed[$target->value];

        if (!$user->hasPermission($permission)) {
            throw new BusinessException("You are not allowed to move a delivery to '{$target->value}'.");
        }

        $this->assertActor(delivery: $delivery, user: $user, actor: $actor);
    }

    private function assertActor(Delivery $delivery, User $user, string $actor): void
    {
        if ($actor === self::ACTOR_OWNER_DELIVERYMAN && $delivery->delivery_man_id !== $user->id) {
            throw new BusinessException('This delivery is assigned to another delivery man.');
        }

        if ($actor === self::ACTOR_OWNER_CLIENT && $delivery->client_id !== $user->id) {
            throw new BusinessException('This delivery belongs to another client.');
        }
    }
}
