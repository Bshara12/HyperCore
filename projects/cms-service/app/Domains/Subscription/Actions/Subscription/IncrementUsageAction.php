<?php

namespace App\Domains\Subscription\Actions\Usage;

use Exception;
use App\Domains\Subscription\DTOs\Usage\CheckUsageLimitDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class IncrementUsageAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        CheckUsageLimitDTO $dto
    ): void {

        $subscription = $this->repository
            ->findActiveSubscription(
                $dto->userId,
                $dto->projectId
            );

        if (!$subscription) {

            throw new Exception(
                'No active subscription.'
            );
        }

        $this->repository
            ->incrementFeatureUsage(
                $subscription->id,
                $dto->featureKey,
                $dto->requestedAmount
            );
    }
}