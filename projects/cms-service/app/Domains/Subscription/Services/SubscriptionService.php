<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Subscription\CancelSubscriptionAction;
use App\Domains\Subscription\Actions\Subscription\GetProjectSubscriptionStatsAction;
use App\Domains\Subscription\Actions\Subscription\ListProjectSubscriptionsAction;
use App\Domains\Subscription\Actions\Subscription\ListUserSubscriptionsAction;
use App\Domains\Subscription\Actions\Subscription\RenewSubscriptionAction;
use App\Domains\Subscription\Actions\Subscription\ShowSubscriptionAction;
use App\Domains\Subscription\Actions\Subscription\SubscribeUserAction;
use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\ListProjectSubscriptionsDTO;
use App\Domains\Subscription\DTOs\Subscription\ListSubscriptionsDTO;
use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\ShowSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Models\Subscription;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SubscriptionService
{
    public function __construct(
        private SubscribeUserAction $subscribeUserAction,
        private RenewSubscriptionAction $renewSubscriptionAction,
        private CancelSubscriptionAction $cancelSubscriptionAction,
        private ListUserSubscriptionsAction $listUserSubscriptionsAction,
        private ShowSubscriptionAction $showSubscriptionAction,
        private ListProjectSubscriptionsAction $listProjectSubscriptionsAction,
        private GetProjectSubscriptionStatsAction $projectStatsAction
    ) {}

    public function subscribe(
        SubscribeUserDTO $dto
    ): Subscription {

        return $this->subscribeUserAction
            ->execute($dto);
    }

    public function renew(
        RenewSubscriptionDTO $dto
    ): Subscription {

        return $this->renewSubscriptionAction
            ->execute($dto);
    }

    public function cancel(
        CancelSubscriptionDTO $dto
    ): Subscription {

        return $this->cancelSubscriptionAction
            ->execute($dto);
    }

    public function listForUser(
        ListSubscriptionsDTO $dto
    ): Collection {

        return $this->listUserSubscriptionsAction
            ->execute($dto);
    }

    public function show(
        ShowSubscriptionDTO $dto
    ): Subscription {

        return $this->showSubscriptionAction
            ->execute($dto);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin — project-scoped, not user-scoped
    |--------------------------------------------------------------------------
    */

    public function listForProject(
        ListProjectSubscriptionsDTO $dto
    ): LengthAwarePaginator {

        return $this->listProjectSubscriptionsAction
            ->execute($dto);
    }

    /**
     * @return array{by_status: array<string, int>, total: int}
     */
    public function statsForProject(
        int $projectId
    ): array {

        return $this->projectStatsAction
            ->execute($projectId);
    }
}
