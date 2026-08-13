<?php

namespace App\Domains\Subscription\Actions\Plan;

use App\Domains\Subscription\DTOs\Plan\ListPlansDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionPlanRepositoryInterface;
use Illuminate\Support\Collection;

class ListActivePlansAction
{
    public function __construct(
        private SubscriptionPlanRepositoryInterface $repository
    ) {}

    public function execute(
        ListPlansDTO $dto
    ): Collection {

        return $this->repository
            ->getActivePlans($dto->projectId);
    }
}