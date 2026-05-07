<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationBatchResource;
use App\Domains\Notifications\Resources\NotificationResource;
use App\Domains\Notifications\Services\NotificationBatchService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Http\Requests\Domains\Notifications\Requests\CreateBulkNotificationRequest;
use App\Http\Requests\Domains\Notifications\Requests\CreateNotificationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class InternalNotificationController extends Controller
{
    public function __construct(
        private readonly NotificationWriteService $writeService,
        private readonly NotificationBatchService $batchService
    ) {}

    public function storeSystem(CreateNotificationRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $notification = $this->writeService->create(
            actor: $actor,
            payload: $request->validated()
        );

        return response()->json([
            'data' => new NotificationResource($notification),
        ], 201);
    }

    public function storeBulk(CreateBulkNotificationRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $batch = $this->batchService->create($actor, $request->validated());

        return response()->json([
            'data' => new NotificationBatchResource($batch),
        ], 202);
    }
}
