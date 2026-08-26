<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Plan\CreatePlanAction;
use App\Domains\Subscription\Actions\Plan\ListActivePlansAction;
use App\Domains\Subscription\Actions\Plan\ShowPlanAction;
use App\Domains\Subscription\DTOs\Plan\CreatePlanDTO;
use App\Domains\Subscription\DTOs\Plan\ListPlansDTO;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Collection;

class PlanService
{
    public function __construct(
        private CreatePlanAction $createPlanAction,
        private ListActivePlansAction $listActivePlansAction,
        private ShowPlanAction $showPlanAction
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
