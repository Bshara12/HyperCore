<?php

namespace App\Domains\Subscription\Actions\Subscription;

use Exception;
use App\Models\Subscription;
use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class CancelSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        CancelSubscriptionDTO $dto
    ): Subscription {

        $subscription = $dto->subscription;

        $this->ensureOwnership(
            $dto->userId,
            $subscription
        );

        $this->ensureNotAlreadyCancelled(
            $subscription
        );

        return $this->repository->cancel(
            $subscription,
            [

                'status' => Subscription::STATUS_CANCELLED,

                'cancelled_at' => now(),

                'auto_renew' => false,

                'metadata' => array_merge(
                    $subscription->metadata ?? [],
                    [
                        'cancel_reason' => $dto->reason
                    ]
                )
            ]
        );
    }

    private function ensureOwnership(
        int $userId,
        Subscription $subscription
    ): void {

        if (
            $subscription->user_id !== $userId
        ) {

            throw new Exception(
                'Unauthorized subscription.'
            );
        }
    }

    private function ensureNotAlreadyCancelled(
        Subscription $subscription
    ): void {

        if (
            $subscription->status
            === Subscription::STATUS_CANCELLED
        ) {

            throw new Exception(
                'Subscription already cancelled.'
            );
        }
    }
}