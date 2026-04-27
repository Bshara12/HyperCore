<?php

namespace App\Http\Controllers;

use App\Domains\Search\Requests\PopularSearchRequest;
use App\Domains\Search\Services\PopularSearchService;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

class PopularSearchController extends Controller
{
    public function __construct(
        private PopularSearchService $service,
    ) {}

    public function __invoke(PopularSearchRequest $request): JsonResponse
    {
        $projectId = CurrentProject::id();

        if (!$projectId) {
            return response()->json(['message' => 'X-Project-Id header is required.'], 400);
        }

        $result = $this->service->getPopular(
            projectId: $projectId,
            language:  $request->language(),
            window:    $request->window(),
            type:      $request->type(),
            limit:     $request->limit(),
        );

        return response()
            ->json($result->toArray())
            ->header('Cache-Control', 'public, max-age=300')
            ->header('X-Popular-Source', $result->source)
            ->header('X-Popular-Took', round($result->tookMs, 2) . 'ms');
    }
}