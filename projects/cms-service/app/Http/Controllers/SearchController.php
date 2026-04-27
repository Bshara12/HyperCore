<?php

namespace App\Http\Controllers;

use App\Domains\Search\DTOs\SearchQueryDTO;
use App\Domains\Search\Requests\SearchRequest;
use App\Domains\Search\Services\SearchService;
use App\Http\Controllers\Controller;
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

        // ─── استخراج الـ user بأمان ───────────────────────────────────
        $user   = $request->attributes->get('auth_user');
        $userId = $this->resolveUserId($user);

        // ─── تسجيل للـ debugging (احذفه في production) ───────────────
        Log::debug('Search request user context', [
            'user_raw'   => $user,
            'user_id'    => $userId,
            'project_id' => $projectId,
            'keyword'    => $request->keyword(),
        ]);

        $dto = new SearchQueryDTO(
            keyword:      $request->keyword(),
            projectId:    $projectId,
            language:     $request->language(),
            page:         $request->page(),
            perPage:      $request->perPage(),
            dataTypeSlug: $request->dataTypeSlug(),
            userId:       $userId,
            sessionId:    $this->resolveSessionId($user, $request),
        );

        $result = $this->searchService->search($dto);

        return response()->json($result->toArray());
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * استخراج user_id بأمان من أي structure
     * يدعم: ['id' => 1] أو ['data' => ['id' => 1]] أو ['user' => ['id' => 1]]
     */
    private function resolveUserId(mixed $user): ?int
    {
        if (empty($user)) {
            return null;
        }

        // الـ structure المباشر: $user['id']
        if (isset($user['id']) && is_numeric($user['id'])) {
            return (int) $user['id'];
        }

        // الـ structure المغلف: $user['data']['id']
        if (isset($user['data']['id']) && is_numeric($user['data']['id'])) {
            return (int) $user['data']['id'];
        }

        // الـ structure البديل: $user['user']['id']
        if (isset($user['user']['id']) && is_numeric($user['user']['id'])) {
            return (int) $user['user']['id'];
        }

        Log::warning('SearchController: could not resolve user_id', [
            'user_structure' => array_keys((array) $user),
        ]);

        return null;
    }

    /**
     * استخراج sessionId للـ guest tracking
     * نستخدم X-Session-Id header كمصدر موثوق بدل sessions array
     */
    private function resolveSessionId(mixed $user, SearchRequest $request): ?string
    {
        // 1. إذا كان عندنا user_id، لا نحتاج session (الـ personalization تعمل بـ userId)
        if ($this->resolveUserId($user) !== null) {
            return null;
        }

        // 2. للـ guests: خذ من X-Session-Id header إذا وُجد
        $headerSession = $request->header('X-Session-Id');
        if (!empty($headerSession)) {
            return $headerSession;
        }

        // 3. Fallback: أول session من الـ user object
        if (isset($user['sessions'][0]['id'])) {
            return (string) $user['sessions'][0]['id'];
        }

        return null;
    }
}