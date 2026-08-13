<?php

namespace App\Domains\Subscription\Actions\Subscription;

use App\Domains\Subscription\DTOs\Subscription\ListSubscriptionsDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use Illuminate\Support\Collection;

class ListUserSubscriptionsAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        ListSubscriptionsDTO $dto
    ): Collection {

        return $this->repository->findForUser(
            $dto->userId,
            $dto->projectId,
            $dto->status
        );
    }
}