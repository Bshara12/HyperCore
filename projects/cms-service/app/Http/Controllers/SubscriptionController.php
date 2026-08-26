<?php

namespace App\Http\Controllers;

use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Requests\Subscription\CancelSubscriptionRequest;
use App\Domains\Subscription\Requests\Subscription\RenewSubscriptionRequest;
use App\Domains\Subscription\Requests\Subscription\SubscribeUserRequest;
use App\Domains\Subscription\Services\SubscriptionService;
use App\Models\Subscription;
use App\Domains\Subscription\Requests\Subscription\ListProjectSubscriptionsRequest;
use App\Domains\Subscription\Requests\Subscription\ListSubscriptionsRequest;
use App\Domains\Subscription\DTOs\Subscription\ListSubscriptionsDTO;
use App\Domains\Subscription\DTOs\Subscription\ListProjectSubscriptionsDTO;
use App\Domains\Subscription\DTOs\Subscription\ShowSubscriptionDTO;


class SubscriptionController extends Controller
{
    public function __construct(
        private SubscriptionService $service
    ) {}

    public function store(
        SubscribeUserRequest $request
    ) {

        $dto = SubscribeUserDTO::fromRequest(
            $request
        );

        $subscription = $this->service
            ->subscribe($dto);

        return response()->json([
            'data' => $subscription,
        ], 201);
    }

    public function renew(
        RenewSubscriptionRequest $request,
        Subscription $subscription
    ) {

        $dto = RenewSubscriptionDTO::fromRequest(
            $request,
            $subscription
        );

        $subscription = $this->service
            ->renew($dto);

        return response()->json([
            'data' => $subscription,
        ]);
    }

    public function cancel(
        CancelSubscriptionRequest $request,
        Subscription $subscription
    ) {

        $dto = CancelSubscriptionDTO::fromRequest(
            $request,
            $subscription
        );

        $subscription = $this->service
            ->cancel($dto);

        return response()->json([
            'data' => $subscription,
        ]);
    }

    public function index(
        ListSubscriptionsRequest $request
    ) {

        $dto = ListSubscriptionsDTO::fromRequest(
            $request
        );

        $subscriptions = $this->service
            ->listForUser($dto);

        return response()->json([
            'data' => $subscriptions,
        ]);
    }

    public function show(
        Subscription $subscription
    ) {

        $dto = ShowSubscriptionDTO::fromSubscription(
            $subscription
        );

        $subscription = $this->service
            ->show($dto);

        return response()->json([
            'data' => $subscription,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Admin dashboard — every subscription of the current project.
    |
    | index() above is the end-user view (own subscriptions only). This one is
    | scoped to the project resolved from the X-Project-Key header instead, so
    | an operator can see the project's whole subscriber list.
    |--------------------------------------------------------------------------
    */
    public function projectIndex(
        ListProjectSubscriptionsRequest $request
    ) {

        $dto = ListProjectSubscriptionsDTO::fromRequest(
            $request
        );

        $subscriptions = $this->service
            ->listForProject($dto);

        return response()->json([
            'data' => $subscriptions->items(),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
            'stats' => $this->service
                ->statsForProject($dto->projectId),
        ]);
    }
}
