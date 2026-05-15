<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\Resources\NotificationDeliveryResource;
use App\Domains\Notifications\Services\NotificationWebhookCallbackService;
use App\Http\Requests\Domains\Notifications\Requests\WebhookDeliveryCallbackRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class WebhookCallbackController extends Controller
{
    public function __construct(
        private readonly NotificationWebhookCallbackService $callbackService
    ) {}

    public function store(WebhookDeliveryCallbackRequest $request): JsonResponse
    {
        $delivery = $this->callbackService->handle(
            $request,
            $request->validated()
        );

        return response()->json([
            'data' => new NotificationDeliveryResource($delivery),
        ]);
    }
}
