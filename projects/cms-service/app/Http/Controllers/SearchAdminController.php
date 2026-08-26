<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Search\Services\AI\QueryInterpreterInterface;
use App\Domains\Search\Services\SearchExplainService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SearchAdminController extends Controller
{
    public function __construct(
        private readonly SearchExplainService $explainer,
        private readonly QueryInterpreterInterface $interpreter,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/search/debug — تفكيك بحث واحد
    // ─────────────────────────────────────────────────────────────────

    public function debug(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'min:1', 'max:500'],
            'language' => ['sometimes', 'string', 'max:10'],
            'project_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json($this->explainer->explain(
            keyword: (string) $request->input('keyword'),
            language: (string) $request->input('language', 'en'),
            projectId: (int) $request->input('project_id'),
            userId: $request->input('user_id') !== null ? (int) $request->input('user_id') : null,
            limit: (int) $request->input('limit', 10),
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/search/terms — وزن كل مصطلح في هذا المتن
    // ─────────────────────────────────────────────────────────────────

    public function terms(Request $request): JsonResponse
    {
        $request->validate([
            'keyword' => ['required', 'string', 'min:1', 'max:500'],
            'language' => ['sometimes', 'string', 'max:10'],
            'project_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json($this->explainer->explainTermWeights(
            (string) $request->input('keyword'),
            (int) $request->input('project_id'),
            (string) $request->input('language', 'en'),
        ));
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /admin/search/logs
    // ─────────────────────────────────────────────────────────────────

    public function logs(Request $request): JsonResponse
    {
        $start = microtime(true);
        $filter = (string) $request->input('filter', 'all');
        $limit = min((int) $request->input('limit', 50), 200);
        $page = max(1, (int) $request->input('page', 1));

        $query = DB::table('user_search_logs as usl')
            ->select([
                'usl.id', 'usl.keyword', 'usl.language', 'usl.project_id',
                'usl.user_id', 'usl.results_count', 'usl.detected_intent',
                'usl.intent_confidence', 'usl.searched_at',
            ])
            ->orderByDesc('usl.searched_at');

        /*
         | الوسيطان كانا يُرسَلان من الواجهة ويُتجاهلان هنا بصمت.
         |
         | فكان المشغّل يختار نيّة أو لغة ويرى القائمة كما هي، فيستنتج
         | أن كل السجلات تحمل ما اختاره — وهو استنتاج خاطئ تماماً.
         | الفلتر الذي لا يفلتر أسوأ من غيابه لأنه يكذب بصمت.
         */
        if (($intent = $request->input('intent')) !== null && $intent !== '') {
            $query->where('usl.detected_intent', (string) $intent);
        }

        if (($language = $request->input('lang')) !== null && $language !== '') {
            $query->where('usl.language', (string) $language);
        }

        match ($filter) {
            'zero_results' => $query->where('usl.results_count', 0),
            'high_frequency' => $query->select([
                'usl.keyword', 'usl.language', 'usl.project_id',
                DB::raw('COUNT(*) as search_count'),
                DB::raw('AVG(usl.results_count) as avg_results'),
                DB::raw('MAX(usl.searched_at) as last_searched'),
            ])
                ->groupBy('usl.keyword', 'usl.language', 'usl.project_id')
                ->orderByDesc('search_count'),
            default => null,
        };

        $total = $query->count();
        $rows = $query->limit($limit)->offset(($page - 1) * $limit)->get();

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'filter' => $filter,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ],
            'logs' => $rows->map(static fn ($row): array => (array) $row)->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /admin/search/problems
    // ─────────────────────────────────────────────────────────────────

    public function problems(Request $request): JsonResponse
    {
        $start = microtime(true);
        $projectId = $request->input('project_id');
        $days = (int) $request->input('days', 7);
        $since = now()->subDays($days);

        $base = fn () => DB::table('user_search_logs')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->where('searched_at', '>=', $since);

        $zeroResults = (clone $base())
            ->select('keyword', 'language', DB::raw('COUNT(*) as count'))
            ->where('results_count', 0)
            ->groupBy('keyword', 'language')
            ->having('count', '>=', 2)
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $lowResults = (clone $base())
            ->select(
                'keyword',
                'language',
                DB::raw('AVG(results_count) as avg_results'),
                DB::raw('COUNT(*) as search_count')
            )
            ->where('results_count', '>', 0)
            ->where('results_count', '<', 3)
            ->groupBy('keyword', 'language')
            ->having('search_count', '>=', 2)
            ->orderByDesc('search_count')
            ->limit(20)
            ->get();

        $overview = (clone $base())
            ->selectRaw('
                COUNT(*) as total_searches,
                SUM(CASE WHEN results_count = 0 THEN 1 ELSE 0 END) as zero_result_count,
                AVG(results_count) as avg_results,
                COUNT(DISTINCT keyword) as unique_queries
            ')
            ->first();

        $totalSearches = (int) ($overview->total_searches ?? 0);
        $zeroCount = (int) ($overview->zero_result_count ?? 0);

        /*
         | الخطط المحفوظة تكشف الاستعلامات التي عجز عنها المسار المحلّي
         | وأنقذها النموذج. أكثرها إصابةً هي المرشَّح الأول للنقل إلى
         | المعجم — فيُستغنى عن النموذج فيها نهائياً.
         */
        $refinerReliance = DB::table('search_query_plans')
            ->select('original_query', 'language', 'hit_count', 'confidence', 'provider')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderByDesc('hit_count')
            ->limit(15)
            ->get();

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'period_days' => $days,
            'overview' => [
                'total_searches' => $totalSearches,
                'zero_result_count' => $zeroCount,
                'zero_result_rate' => $totalSearches > 0
                    ? round($zeroCount / $totalSearches * 100, 1).'%'
                    : '0%',
                'avg_results' => round((float) ($overview->avg_results ?? 0), 2),
                'unique_queries' => (int) ($overview->unique_queries ?? 0),
            ],
            'zero_results' => $zeroResults->map(static fn ($r): array => (array) $r)->values(),
            'low_results' => $lowResults->map(static fn ($r): array => (array) $r)->values(),
            'lexicon_candidates' => $refinerReliance->map(static fn ($r): array => (array) $r)->values(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // POST /admin/search/ai/re-run — اختبار المزوّد مباشرةً
    // ─────────────────────────────────────────────────────────────────

    public function aiReRun(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:1', 'max:500'],
            'language' => ['sometimes', 'string', 'max:10'],
        ]);

        $start = microtime(true);
        $query = (string) $request->input('query');
        $language = (string) $request->input('language', 'en');

        $interpretation = $this->interpreter->interpret($query, $language);

        return response()->json([
            'execution_time_ms' => round((microtime(true) - $start) * 1000, 2),
            'input' => ['query' => $query, 'language' => $language],
            'interpretation' => $interpretation,
            'status' => $interpretation === null ? 'no_interpretation' : 'ok',
        ], $interpretation === null ? 200 : 200);
    }

    // ─────────────────────────────────────────────────────────────────
    // GET /admin/search/config — الضبط الفعّال
    // ─────────────────────────────────────────────────────────────────

    /**
     * ملاحظة: القراءة فقط.
     *
     * كان هناك setConfig يكتب عبر config() في وقت التشغيل. وهذا لا
     * يفعل شيئاً ذا معنى: الكتابة تعيش داخل الطلب الواحد وتموت معه،
     * فيرى المشغّل استجابةً تؤكّد التغيير بينما لا يتغيّر شيء لأي
     * مستخدم آخر — وهي أسوأ من غياب الميزة لأنها تكذب.
     *
     * الضبط يُغيَّر في .env ويُعاد تحميل الإعدادات.
     */
    public function getConfig(): JsonResponse
    {
        return response()->json([
            'retrieval' => config('search.retrieval'),
            'ranking' => config('search.ranking'),
            'understanding' => config('search.understanding'),
            'indexing' => config('search.indexing'),
            'ai' => [
                'enabled' => (bool) config('search.ai.enabled'),
                'timeout_seconds' => config('search.ai.timeout_seconds'),
                'plan_cache_days' => config('search.ai.plan_cache_days'),
                'circuit_breaker' => config('search.ai.circuit_breaker'),
                'providers_configured' => array_values(array_filter([
                    ! empty(config('services.gemini.api_key')) ? 'gemini' : null,
                    ! empty(config('services.openrouter.api_key')) ? 'openrouter' : null,
                ])),
            ],
            'environment' => app()->environment(),
        ]);
    }
}
