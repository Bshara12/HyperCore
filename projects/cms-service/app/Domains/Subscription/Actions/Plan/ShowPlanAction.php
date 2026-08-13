<?php

namespace App\Domains\Subscription\Actions\Plan;

use App\Domains\Subscription\Repositories\Interface\SubscriptionPlanRepositoryInterface;
use App\Exceptions\SubscriptionPlanNotFoundException;
use App\Models\SubscriptionPlan;

class ShowPlanAction
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $repository
    ) {}

    public function execute(
        int $id
    ): SubscriptionPlan {

        $plan = $this->repository
            ->findById($id);

        if (! $plan) {
            throw new SubscriptionPlanNotFoundException($id);
        }

        return $plan;
    }
}