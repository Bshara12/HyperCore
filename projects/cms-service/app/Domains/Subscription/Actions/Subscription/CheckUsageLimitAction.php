<?php

namespace App\Domains\Subscription\Actions\Usage;

use App\Models\SubscriptionFeature;
use App\Domains\Subscription\DTOs\Usage\CheckUsageLimitDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class CheckUsageLimitAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        CheckUsageLimitDTO $dto
    ): bool {

        $subscription = $this->repository
            ->findActiveSubscription(
                $dto->userId,
                $dto->projectId
            );

        if (!$subscription) {
            return false;
        }

        $feature = $subscription
            ->plan
            ->features
            ->firstWhere(
                'feature_key',
                $dto->featureKey
            );

        if (!$feature) {
            return false;
        }

        $limit = $this->resolveLimit(
            $feature
        );

        $used = $this->repository
            ->getFeatureUsage(
                $subscription->id,
                $dto->featureKey
            );

        return (
            ($used + $dto->requestedAmount)
            <=
            $limit
        );
    }

    private function resolveLimit(
        SubscriptionFeature $feature
    ): int {

        return match ($feature->feature_type) {

            'number' => (int)
                $feature->feature_value,

            default => 0
        };
    }
}