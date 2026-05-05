<?php

namespace App\Http\Controllers;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\Requests\SearchRequest;
use App\Domains\Search\Services\SearchService;
use App\Support\CurrentProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
  public function __construct(
    private SearchService $searchService,
  ) {}

  public function __invoke(SearchRequest $request): JsonResponse
  {
    $projectId = CurrentProject::id();

    if (!$projectId) {
      return response()->json(['message' => 'X-Project-Id header is required.'], 400);
    }

    $user = $request->attributes->get('auth_user');

    /*
     * حماية من null أو بنية مختلفة
     * إذا user = null → guest search بدون personalization
     */
    $userId    = $this->resolveUserId($user);
    $sessionId = $this->resolveSessionId($user);

    $dto = new SearchQueryDTO(
      keyword: $request->keyword(),
      projectId: $projectId,
      language: $request->language(),
      page: $request->page(),
      perPage: $request->perPage(),
      dataTypeSlug: $request->dataTypeSlug(),
      userId: $userId,
      sessionId: $sessionId,
    );

    $result = $this->searchService->search($dto);

    return response()->json($result->toArray());
  }

  // ─────────────────────────────────────────────────────────────────

  private function resolveUserId(mixed $user): ?int
  {
    if (empty($user)) {
      return null;
    }

    // دعم بنيات مختلفة للـ user object
    $id = $user['id']
      ?? $user['data']['id']
      ?? $user['user']['id']
      ?? null;

    return $id !== null ? (int) $id : null;
  }

  private function resolveSessionId(mixed $user): ?string
  {
    if (empty($user)) {
      return null;
    }

    return $user['sessions'][0]['id']
      ?? $user['session_id']
      ?? null;
  }
}
