<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationSubscriptionResource;
use App\Domains\Notifications\Services\NotificationSubscriptionService;
use App\Http\Requests\Domains\Notifications\Requests\SyncNotificationSubscriptionsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InternalSubscriptionController extends Controller
{
    public function __construct(
        private readonly NotificationSubscriptionService $subscriptionService
    ) {}

    public function sync(SyncNotificationSubscriptionsRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $subscriptions = $this->subscriptionService->syncForProject(
            $actor,
            $request->validated('subscriptions')
        );

        return response()->json([
            'data' => NotificationSubscriptionResource::collection($subscriptions),
        ]);
    }
}
