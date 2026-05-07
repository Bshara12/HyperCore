<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationSubscriptionResource;
use App\Domains\Notifications\Services\NotificationSubscriptionService;
use App\Http\Requests\Domains\Notifications\Requests\StoreNotificationSubscriptionRequest;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly NotificationSubscriptionService $subscriptionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $subscriptions = $this->subscriptionService->listForActor($actor);

        return response()->json([
            'data' => NotificationSubscriptionResource::collection($subscriptions),
        ]);
    }

    public function store(StoreNotificationSubscriptionRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $subscription = $this->subscriptionService->createForActor($actor, $request->validated());

        return response()->json([
            'data' => new NotificationSubscriptionResource($subscription),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $subscription = $this->subscriptionService->findForActor($actor, $id);

        return response()->json([
            'data' => new NotificationSubscriptionResource($subscription),
        ]);
    }

    public function update(UpdateNotificationSubscriptionRequest $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $subscription = $this->subscriptionService->findForActor($actor, $id);

        $subscription = $this->subscriptionService->updateForActor($actor, $subscription, $request->validated());

        return response()->json([
            'data' => new NotificationSubscriptionResource($subscription),
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $subscription = $this->subscriptionService->findForActor($actor, $id);

        $this->subscriptionService->deleteForActor($actor, $subscription);

        return response()->json([
            'message' => 'Subscription deleted successfully.',
        ]);
    }
}
