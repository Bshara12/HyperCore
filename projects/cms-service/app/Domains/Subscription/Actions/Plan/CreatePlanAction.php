<?php

namespace App\Domains\Subscription\Actions\Plan;

use Exception;
use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionPlanRepositoryInterface;
use App\Models\SubscriptionPlan;

class CreatePlanAction
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $repository
    ) {}

    public function execute(
        CreatePlanDTO $dto
    ): SubscriptionPlan {

        $this->ensureSlugIsUnique($dto);

        return $this->repository
            ->createWithFeatures($dto);
    }

    private function ensureSlugIsUnique(
        CreatePlanDTO $dto
    ): void {

        $exists = $this->repository->slugExists(
            projectId: $dto->projectId,
            slug: $dto->slug
        );

        if ($exists) {
            throw new Exception(
                'Subscription plan slug already exists.'
            );
        }
    }
}