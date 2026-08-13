<?php

namespace App\Domains\Subscription\Actions\Subscription;

use App\Domains\Subscription\DTOs\Subscription\ShowSubscriptionDTO;
use App\Domains\Subscription\Repositories\Interface\SubscriptionRepositoryInterface;
use App\Exceptions\SubscriptionAccessDeniedException;
use App\Models\Subscription;

class ShowSubscriptionAction
{
    public function __construct(
        private SubscriptionRepositoryInterface $repository
    ) {}

    public function execute(
        ShowSubscriptionDTO $dto
    ): Subscription {

        if ($dto->subscription->user_id !== $dto->userId) {
            throw new SubscriptionAccessDeniedException(
                $dto->subscription->id
            );
        }

        return $this->repository->findByIdWithUsages(
            $dto->subscription->id
        );
    }
}