<?php

namespace App\Http\Controllers;

use App\Domains\Search\Services\AIQueryEnhancer;
use App\Domains\Search\Services\SearchDebugService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchAdminController extends Controller
{
    public function __construct(
        private SearchDebugService $debugService,
        private AIQueryEnhancer    $aiEnhancer,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // 1. POST /admin/search/debug
    // ─────────────────────────────────────────────────────────────────

    public function debug(Request $request): JsonResponse
    {
        $request->validate([
            'keyword'    => ['required', 'string', 'min:1', 'max:500'],
            'language'   => ['sometimes', 'string', 'size:2'],
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->debugService->runDebugPipeline(
            keyword:   $request->input('keyword'),
            language:  $request->input('language', 'en'),
            projectId: (int) $request->input('project_id'),
        );

        return response()->json($result);
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. GET /admin/search/logs
    // ─────────────────────────────────────────────────────────────────

    public function logs(Request $request): JsonResponse
    {
        $start  = microtime(true);
        $filter = $request->input('filter', 'all');
        $limit  = min((int) $request->input('limit', 50), 200);
        $page   = (int) $request->input('page', 1);

        $query = DB::table('user_search_logs as usl')
            ->select([
                'usl.id',
                'usl.keyword',
                'usl.language',
                'usl.project_id',
                'usl.user_id',
                'usl.results_count',
                'usl.detected_intent',
                'usl.intent_confidence',
                'usl.searched_at',
            ])
            ->orderByDesc('usl.searched_at');

        // ─── فلاتر ────────────────────────────────────────────────────
        match ($filter) {
            'zero_results'   => $query->where('usl.results_count', 0),
            'ai_used'        => $query->where('usl.results_count', '>', 0)
                                      ->where('usl.intent_confidence', '>=', 0.5),
            'high_frequency' => $query->select([
                                    'usl.keyword',
                                    'usl.language',
                                    'usl.project_id',
                                    DB::raw('COUNT(*) as search_count'),
                                    DB::raw('AVG(usl.results_count) as avg_results'),
                                    DB::raw('MAX(usl.searched_at) as last_searched'),
                                ])
                                ->groupBy('usl.keyword', 'usl.language', 'usl.project_id')
                                ->orderByDesc('search_count'),
            default          => null,
        };

        $total  = $query->count();
        $offset = ($page - 1) * $limit;
        $rows   = $query->limit($limit)->offset($offset)->get();

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'filter'            => $filter,
            'pagination'        => [
                'total'    => $total,
                'page'     => $page,
                'limit'    => $limit,
                'pages'    => (int) ceil($total / $limit),
            ],
            'logs'              => $rows->map(fn($row) => (array) $row)->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. GET /admin/search/problems
    // ─────────────────────────────────────────────────────────────────

    public function problems(Request $request): JsonResponse
    {
        $start     = microtime(true);
        $projectId = $request->input('project_id');
        $days      = (int) $request->input('days', 7);

        $since = now()->subDays($days);

        $baseQuery = fn() => DB::table('user_search_logs')
            ->when($projectId, fn($q) => $q->where('project_id', $projectId))
            ->where('searched_at', '>=', $since);

        // ─── Zero Results ─────────────────────────────────────────────
        $zeroResults = (clone $baseQuery())
            ->select('keyword', 'language', DB::raw('COUNT(*) as count'))
            ->where('results_count', 0)
            ->groupBy('keyword', 'language')
            ->having('count', '>=', 2)
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        // ─── Low Results ──────────────────────────────────────────────
        $lowResults = (clone $baseQuery())
            ->select('keyword', 'language', DB::raw('AVG(results_count) as avg_results'),
                     DB::raw('COUNT(*) as search_count'))
            ->where('results_count', '>', 0)
            ->where('results_count', '<', 3)
            ->groupBy('keyword', 'language')
            ->having('search_count', '>=', 2)
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        // ─── High AI Usage ────────────────────────────────────────────
        $highAiUsage = (clone $baseQuery())
            ->select('keyword', 'language', DB::raw('COUNT(*) as count'),
                     DB::raw('AVG(intent_confidence) as avg_confidence'))
            ->where('intent_confidence', '<', 0.4)
            ->where('results_count', '>', 0)
            ->groupBy('keyword', 'language')
            ->having('count', '>=', 3)
            ->orderByDesc('count')
            ->limit(15)
            ->get();

        // ─── Suspicious Queries ───────────────────────────────────────
        $suspicious = (clone $baseQuery())
            ->select('keyword', 'language', DB::raw('COUNT(*) as count'))
            ->whereRaw('CHAR_LENGTH(keyword) > 100')
            ->orWhereRaw("keyword REGEXP '[+\\-><\\(\\)~*\"@]{3,}'")
            ->groupBy('keyword', 'language')
            ->limit(10)
            ->get();

        // ─── Stats Overview ───────────────────────────────────────────
        $overview = (clone $baseQuery())
            ->selectRaw("
                COUNT(*) as total_searches,
                SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_result_count,
                AVG(results_count) as avg_results,
                COUNT(DISTINCT keyword) as unique_queries
            ")
            ->first();

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'period_days'       => $days,
            'overview'          => [
                'total_searches'    => (int) ($overview->total_searches ?? 0),
                'zero_result_count' => (int) ($overview->zero_result_count ?? 0),
                'zero_result_rate'  => $overview->total_searches > 0
                    ? round($overview->zero_result_count / $overview->total_searches * 100, 1) . '%'
                    : '0%',
                'avg_results'       => round((float) ($overview->avg_results ?? 0), 2),
                'unique_queries'    => (int) ($overview->unique_queries ?? 0),
            ],
            'zero_results'      => $zeroResults->map(fn($r) => (array) $r)->values(),
            'low_results'       => $lowResults->map(fn($r) => (array) $r)->values(),
            'high_ai_usage'     => $highAiUsage->map(fn($r) => (array) $r)->values(),
            'suspicious_queries'=> $suspicious->map(fn($r) => (array) $r)->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. POST /admin/search/ai/re-run
    // ─────────────────────────────────────────────────────────────────

    public function aiReRun(Request $request): JsonResponse
    {
        $request->validate([
            'query'    => ['required', 'string', 'min:1', 'max:500'],
            'language' => ['sometimes', 'string', 'size:2'],
        ]);

        $start    = microtime(true);
        $query    = $request->input('query');
        $language = $request->input('language', 'en');

        // ─── مسح الـ cache لإجبار استدعاء جديد ──────────────────────
        $cacheKey = 'ai_enhance:' . md5(mb_strtolower(trim($query)) . ':' . $language);
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        try {
            $rawResult = $this->aiEnhancer->enhance($query, $language);

            return response()->json([
                'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'input'             => ['query' => $query, 'language' => $language],
                'raw_response'      => $rawResult,
                'parsed'            => [
                    'corrected_query'   => $rawResult['correctedQuery'] ?? '',
                    'expanded_keywords' => $rawResult['expandedKeywords'] ?? [],
                    'confidence'        => $rawResult['confidence'] ?? 0,
                    'source'            => $rawResult['source'] ?? '',
                    'has_correction'    => mb_strtolower($rawResult['correctedQuery'] ?? '')
                                       !== mb_strtolower($query),
                    'has_expansion'     => !empty($rawResult['expandedKeywords']),
                ],
                'recommendation'    => $this->getAIRecommendation($rawResult, $query),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
                'input'             => ['query' => $query, 'language' => $language],
                'error'             => $e->getMessage(),
                'status'            => 'failed',
            ], 500);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. POST /admin/search/compare
    // ─────────────────────────────────────────────────────────────────

    public function compare(Request $request): JsonResponse
    {
        $request->validate([
            'query'            => ['required', 'string'],
            'project_id'       => ['required', 'integer'],
            'language'         => ['sometimes', 'string'],
            'mode_a'           => ['sometimes', 'array'],
            'mode_b'           => ['sometimes', 'array'],
        ]);

        $start     = microtime(true);
        $query     = $request->input('query');
        $projectId = (int) $request->input('project_id');
        $language  = $request->input('language', 'en');
        $modeA     = $request->input('mode_a', ['ai_enabled' => false]);
        $modeB     = $request->input('mode_b', ['ai_enabled' => true]);

        // ─── تشغيل Mode A ─────────────────────────────────────────────
        $originalAI = config('search.ai_enabled');
        config(['search.ai_enabled' => $modeA['ai_enabled'] ?? false]);
        $resultA = $this->debugService->runDebugPipeline($query, $language, $projectId);
        $idsA    = array_column($resultA['final']['results'] ?? [], 'entry_id');

        // ─── تشغيل Mode B ─────────────────────────────────────────────
        config(['search.ai_enabled' => $modeB['ai_enabled'] ?? true]);
        $resultB = $this->debugService->runDebugPipeline($query, $language, $projectId);
        $idsB    = array_column($resultB['final']['results'] ?? [], 'entry_id');

        // ─── إعادة الـ config ─────────────────────────────────────────
        config(['search.ai_enabled' => $originalAI]);

        // ─── حساب الفروق ─────────────────────────────────────────────
        $newResults  = array_values(array_diff($idsB, $idsA));
        $lostResults = array_values(array_diff($idsA, $idsB));
        $common      = array_values(array_intersect($idsA, $idsB));

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'query'             => $query,
            'mode_a'            => [
                'config'  => $modeA,
                'total'   => $resultA['final']['total'],
                'ai_used' => $resultA['ai']['triggered'],
                'kb_used' => $resultA['keyboard_fix']['triggered'],
                'results' => $resultA['final']['results'],
            ],
            'mode_b'            => [
                'config'  => $modeB,
                'total'   => $resultB['final']['total'],
                'ai_used' => $resultB['ai']['triggered'],
                'kb_used' => $resultB['keyboard_fix']['triggered'],
                'results' => $resultB['final']['results'],
            ],
            'diff'              => [
                'total_diff'    => $resultB['final']['total'] - $resultA['final']['total'],
                'new_results'   => $newResults,
                'lost_results'  => $lostResults,
                'common'        => count($common),
                'improvement'   => $resultB['final']['total'] > $resultA['final']['total']
                    ? 'mode_b_better'
                    : ($resultA['final']['total'] > $resultB['final']['total'] ? 'mode_a_better' : 'equal'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // 6. GET + POST /admin/search/config
    // ─────────────────────────────────────────────────────────────────

    public function getConfig(): JsonResponse
    {
        return response()->json([
            'search' => [
                'ai_enabled'                   => (bool) config('search.ai_enabled', false),
                'ai_trigger_threshold'         => (int) config('search.ai_trigger_threshold', 0),
                'keyboard_confidence_threshold'=> (float) config('search.keyboard_confidence', 0.4),
            ],
            'queue'  => [
                'connection'    => config('queue.default'),
                'search_queue'  => 'search-tracking',
            ],
            'cache'  => [
                'driver'        => config('cache.default'),
                'ai_ttl'        => 3600,
            ],
            'environment' => app()->environment(),
        ]);
    }

    public function setConfig(Request $request): JsonResponse
    {
        $request->validate([
            'ai_enabled'                    => ['sometimes', 'boolean'],
            'ai_trigger_threshold'          => ['sometimes', 'integer', 'min:0'],
            'keyboard_confidence_threshold' => ['sometimes', 'numeric', 'min:0', 'max:1'],
        ]);

        $changes = [];

        if ($request->has('ai_enabled')) {
            config(['search.ai_enabled' => (bool) $request->input('ai_enabled')]);
            $changes['ai_enabled'] = (bool) $request->input('ai_enabled');
        }

        if ($request->has('ai_trigger_threshold')) {
            config(['search.ai_trigger_threshold' => (int) $request->input('ai_trigger_threshold')]);
            $changes['ai_trigger_threshold'] = (int) $request->input('ai_trigger_threshold');
        }

        if ($request->has('keyboard_confidence_threshold')) {
            config(['search.keyboard_confidence' => (float) $request->input('keyboard_confidence_threshold')]);
            $changes['keyboard_confidence_threshold'] = (float) $request->input('keyboard_confidence_threshold');
        }

        \Illuminate\Support\Facades\Log::info('SearchAdmin: config updated', $changes);

        return response()->json([
            'message' => 'Config updated (runtime only - add to .env for persistence)',
            'changes' => $changes,
            'current' => [
                'ai_enabled'                    => (bool) config('search.ai_enabled'),
                'ai_trigger_threshold'          => (int) config('search.ai_trigger_threshold'),
                'keyboard_confidence_threshold' => (float) config('search.keyboard_confidence'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────

    private function getAIRecommendation(array $result, string $originalQuery): string
    {
        if (($result['confidence'] ?? 0) < 0.3) {
            return 'Low confidence - consider manual review of this query pattern';
        }

        $corrected = $result['correctedQuery'] ?? '';
        if (mb_strtolower($corrected) !== mb_strtolower($originalQuery)) {
            return "Spelling correction applied: '{$originalQuery}' → '{$corrected}'";
        }

        if (!empty($result['expandedKeywords'])) {
            return 'Query expanded with synonyms: ' . implode(', ', $result['expandedKeywords']);
        }

        return 'No changes needed - query seems well-formed';
    }
}