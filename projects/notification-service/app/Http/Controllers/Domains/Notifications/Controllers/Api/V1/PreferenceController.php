<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationPreferenceResource;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PreferenceController extends Controller
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $preferences = $this->preferenceService->listForActor($actor);

        return response()->json([
            'data' => NotificationPreferenceResource::collection($preferences),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);

        $preferences = $this->preferenceService->upsertForActor(
            actor: $actor,
            preferences: $request->validated('preferences')
        );

        return response()->json([
            'data' => NotificationPreferenceResource::collection($preferences),
        ]);
    }
}
