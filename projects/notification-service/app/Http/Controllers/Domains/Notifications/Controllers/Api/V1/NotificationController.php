<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationResource;
use App\Domains\Notifications\Services\NotificationReadService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Http\Requests\Domains\Notifications\Requests\CreateNotificationRequest;
use App\Http\Requests\Domains\Notifications\Requests\MarkAllAsReadRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationReadService $readService,
        private readonly NotificationWriteService $writeService,
    ) {}

    public function store(CreateNotificationRequest $request): JsonResponse
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

    public function index(Request $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $paginator = $this->readService->paginateForActor(
            actor: $actor,
            filters: [
                'status' => $request->query('status'),
                'unread_only' => $request->boolean('unread_only'),
                'topic_key' => $request->query('topic_key'),
            ],
            perPage: $perPage
        );

        return response()->json([
            'data' => NotificationResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $notification = $this->readService->findForActor($actor, $id);

        return response()->json([
            'data' => $notification->toArray(),
        ]);
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $notification = $this->readService->markAsRead($actor, $id);

        return response()->json([
            'data' => $notification->toArray(),
        ]);
    }

    public function markAllAsRead(MarkAllAsReadRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $updatedCount = $this->readService->markAllAsRead($actor);

        return response()->json([
            'data' => [
                'updated_count' => $updatedCount,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $count = $this->readService->unreadCount($actor);

        return response()->json([
            'data' => [
                'count' => $count,
            ],
        ]);
    }
}
