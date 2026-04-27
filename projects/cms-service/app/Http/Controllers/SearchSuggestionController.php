<?php

namespace App\Http\Controllers;

use App\Domains\Search\Requests\SuggestionRequest;
use App\Domains\Search\Services\SuggestionService;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;

class SearchSuggestionController extends Controller
{
    public function __construct(
        private SuggestionService $suggestionService,
    ) {}

    public function __invoke(SuggestionRequest $request): JsonResponse
    {
        $projectId = CurrentProject::id();

        if (!$projectId) {
            return response()->json(['message' => 'X-Project-Id header is required.'], 400);
        }

        $user   = $request->attributes->get('auth_user');
        $userId = isset($user['id']) ? (int) $user['id'] : null;

        $result = $this->suggestionService->getSuggestions(
            prefix:    $request->prefix(),
            projectId: $projectId,
            language:  $request->language(),
            limit:     $request->limit(),
            userId:    $userId,
        );

        return response()
            ->json($result->toArray())
            ->header('Cache-Control', 'private, max-age=60')  // browser cache 60s
            ->header('X-Suggestion-Source', $result->source)  // للـ debugging
            ->header('X-Suggestion-Took', round($result->tookMs, 2) . 'ms');
    }
}