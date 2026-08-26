<?php

namespace App\Domains\Subscription\Actions\Subscription;

use App\Domains\Subscription\DTOs\Subscription\ListProjectSubscriptionsDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ListProjectSubscriptionsAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        ListProjectSubscriptionsDTO $dto
    ): LengthAwarePaginator {

        return $this->repository->paginateForProject(
            projectId: $dto->projectId,
            status: $dto->status,
            planId: $dto->planId,
            userId: $dto->userId,
            perPage: $dto->perPage
        );
    }
}
