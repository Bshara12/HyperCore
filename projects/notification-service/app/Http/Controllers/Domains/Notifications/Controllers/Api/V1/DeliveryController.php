<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationDeliveryResource;
use App\Domains\Notifications\Services\NotificationDeliveryTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeliveryController extends Controller
{
    public function __construct(
        private readonly NotificationDeliveryTrackingService $deliveryService
    ) {}

    public function indexByNotification(Request $request, string $notificationId): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $deliveries = $this->deliveryService->listForNotification($actor, $notificationId);

        return response()->json([
            'data' => NotificationDeliveryResource::collection($deliveries),
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $delivery = $this->deliveryService->findDelivery($actor, $id);

        return response()->json([
            'data' => new NotificationDeliveryResource($delivery),
        ]);
    }
}
