<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Plan\CreatePlanAction;
use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Models\SubscriptionPlan;

class PlanService
{
    public function __construct(
        private CreatePlanAction $createPlanAction
    ) {}

    public function create(
        CreatePlanDTO $dto
    ): SubscriptionPlan {

        return $this->createPlanAction
            ->execute($dto);
    }


    
      public function listActive(
        ListPlansDTO $dto
    ): Collection {

        return $this->listActivePlansAction
            ->execute($dto);
    }

    public function show(
        int $id
    ): SubscriptionPlan {

        return $this->showPlanAction
            ->execute($id);
    }
}
