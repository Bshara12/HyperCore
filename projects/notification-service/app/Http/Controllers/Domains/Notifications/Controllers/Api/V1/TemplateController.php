<?php

namespace App\Http\Controllers\Domains\Notifications\Controllers\Api\V1;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Resources\NotificationTemplateResource;
use App\Domains\Notifications\Services\NotificationTemplateService;
use App\Http\Requests\Domains\Notifications\Requests\StoreNotificationTemplateRequest;
use App\Http\Requests\Domains\Notifications\Requests\UpdateNotificationTemplateRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TemplateController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templateService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $templates = $this->templateService->listForActor($actor);

        return response()->json([
            'data' => NotificationTemplateResource::collection($templates),
        ]);
    }

    public function store(StoreNotificationTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->validated());

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $template = $this->templateService->findForActor($actor, $id);

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    public function update(UpdateNotificationTemplateRequest $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $template = $this->templateService->findForActor($actor, $id);

        $template = $this->templateService->update($template, $request->validated());

        return response()->json([
            'data' => new NotificationTemplateResource($template),
        ]);
    }

    public function activate(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $template = $this->templateService->findForActor($actor, $id);

        return response()->json([
            'data' => new NotificationTemplateResource(
                $this->templateService->activate($template)
            ),
        ]);
    }

    public function deactivate(Request $request, string $id): JsonResponse
    {
        $actor = NotificationActor::fromRequest($request);
        $template = $this->templateService->findForActor($actor, $id);

        return response()->json([
            'data' => new NotificationTemplateResource(
                $this->templateService->deactivate($template)
            ),
        ]);
    }
}
