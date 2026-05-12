<?php

namespace App\Domains\Subscription\Actions\Feature;

use App\Models\SubscriptionFeature;
use App\Domains\Subscription\DTOs\Feature\CheckFeatureAccessDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;

class CheckFeatureAccessAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        CheckFeatureAccessDTO $dto
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

        return $this->resolveFeatureValue(
            $feature
        );
    }

    private function resolveFeatureValue(
        SubscriptionFeature $feature
    ): bool {

        return match ($feature->feature_type) {

            'boolean' => (bool)
                $feature->feature_value,

            'number' => (int)
                $feature->feature_value > 0,

            'json' => !empty(
                $feature->feature_value
            ),

            default => false
        };
    }
}