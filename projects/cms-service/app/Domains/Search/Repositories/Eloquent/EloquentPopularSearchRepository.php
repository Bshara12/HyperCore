<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\PopularSearchQueryDTO;
use App\Models\PopularSearch;
use App\Domains\Search\Repositories\Interfaces\PopularSearchRepositoryInterface;
use App\Domains\Search\Support\WindowFallbackChain;
use Illuminate\Support\Facades\DB;

class EloquentPopularSearchRepository implements PopularSearchRepositoryInterface
{
    /**
     * الحد الأدنى المضمون للنتائج
     * إذا لم نجد هذا العدد → نُطبق fallback
     */
    private const MINIMUM_RESULTS = 3;

    // ─────────────────────────────────────────────────────────────────

    public function getTrending(PopularSearchQueryDTO $dto): array
    {
        /*
         * استراتيجية الـ Trending مع Fallback:
         *
         * 1. نجرب كل window في الـ chain بالترتيب
         * 2. لكل window نستخدم trending_score للترتيب
         * 3. "Soft filter": نسمح بـ count = 0 لكن نُعطي أولوية لـ count > 0
         * 4. نتوقف عند أول window يُعطي MINIMUM_RESULTS نتيجة
         */
        $chain = WindowFallbackChain::getChain($dto->window);

        foreach ($chain as $index => $currentWindow) {
            $result = $this->fetchTrendingForWindow(
                dto:           $dto,
                currentWindow:        $currentWindow,
                fallbackIndex: $index,
            );

            if (count($result['rows']) >= self::MINIMUM_RESULTS) {
                return $result;
            }

            // إذا هذه آخر window في الـ chain، أرجع ما عندنا حتى لو أقل
            if ($index === count($chain) - 1) {
                return $result;
            }
        }

        // Defensive: لا يجب الوصول هنا
        return ['rows' => [], 'window_used' => $dto->window, 'fallback_applied' => false];
    }

    // ─────────────────────────────────────────────────────────────────

    public function getPopular(PopularSearchQueryDTO $dto): array
    {
        /*
         * استراتيجية الـ Popular مع Fallback:
         *
         * مشابهة للـ Trending لكن:
         * - نستخدم alltime_score للترتيب
         * - نُركز على count_all_time بدل count_Xh
         */
        $chain = WindowFallbackChain::getChain($dto->window);

        foreach ($chain as $index => $currentWindow) {
            $result = $this->fetchPopularForWindow(
                dto:           $dto,
                currentWindow:        $currentWindow,
                fallbackIndex: $index,
            );

            if (count($result['rows']) >= self::MINIMUM_RESULTS) {
                return $result;
            }

            if ($index === count($chain) - 1) {
                return $result;
            }
        }

        return ['rows' => [], 'window_used' => $dto->window, 'fallback_applied' => false];
    }

    // ─────────────────────────────────────────────────────────────────
    // Private Fetchers
    // ─────────────────────────────────────────────────────────────────

    /**
     * جلب Trending لـ window محدد
     *
     * الاستراتيجية بدون Hard Filter:
     *
     *   بدل:  WHERE count_24h > 0
     *   نستخدم: ORDER BY trending_score DESC, count_24h DESC, count_all_time DESC
     *
     *   الكلمات ذات count_24h = 0 ستظهر لكن في النهاية بسبب score منخفض
     *   هذا يعني: لا نتائج فارغة، لكن ترتيب ذكي
     *
     *   الـ "Soft Filter" الوحيد المسموح:
     *   WHERE count_all_time > 0
     *   → نتجاهل keywords لم يُبحث عنها نهائياً (بيانات ميتة)
     */
    private function fetchTrendingForWindow(
        PopularSearchQueryDTO $dto,
        string                $currentWindow,
        int                   $fallbackIndex
    ): array {
        $countColumn = WindowFallbackChain::getCountColumn($currentWindow);
        $scoreColumn = 'trending_score';

        /*
         * لماذا لا نستخدم WHERE count_X > 0؟
         *
         * مثال: window=24h، عندنا كلمات بـ count_24h=0 لكن count_7d=100
         * بدون الـ filter → تظهر مرتبة حسب trending_score
         * trending_score يعكس count_7d أيضاً → ستكون في ترتيب جيد
         *
         * مثال آخر: window=24h لكن كل count_24h=0
         * → نأخذ أعلى trending_score بغض النظر عن count_24h
         * → المستخدم يرى نتائج ذات معنى بدل فراغ
         */
        $rows = DB::table('popular_searches')
            ->select([
                'keyword',
                "{$countColumn} as count",
                "{$scoreColumn} as score",
                'count_24h',
                'count_7d',
                'count_30d',
                'count_all_time',
                'trending_score',
                'alltime_score',
                'last_searched_at',
            ])
            ->where('project_id', $dto->projectId)
            ->where('language', $dto->language)
            // Soft filter: تجاهل keywords ميتة نهائياً فقط
            ->where('count_all_time', '>', 0)
            // ترتيب متعدد المستويات لضمان نتائج ذات معنى
            ->orderByDesc($scoreColumn)
            ->orderByDesc($countColumn)
            ->orderByDesc('count_all_time')
            ->limit($dto->limit)
            ->get()
            ->toArray();

        return [
            'rows'             => $rows,
            'window_used'      => $currentWindow,
            'fallback_applied' => $fallbackIndex > 0,
        ];
    }

    /**
     * جلب Popular لـ window محدد
     *
     * للـ Popular نركز على alltime_score
     * لأن الـ "popular" يعني شعبية تاريخية وليس فقط حديثة
     */
    private function fetchPopularForWindow(
        PopularSearchQueryDTO $dto,
        string                $currentWindow,
        int                   $fallbackIndex
    ): array {
        $countColumn = WindowFallbackChain::getCountColumn($currentWindow);
        $scoreColumn = 'alltime_score';

        $rows = DB::table('popular_searches')
            ->select([
                'keyword',
                "{$countColumn} as count",
                "{$scoreColumn} as score",
                'count_24h',
                'count_7d',
                'count_30d',
                'count_all_time',
                'trending_score',
                'alltime_score',
                'last_searched_at',
            ])
            ->where('project_id', $dto->projectId)
            ->where('language', $dto->language)
            ->where('count_all_time', '>', 0)
            ->orderByDesc($scoreColumn)
            ->orderByDesc($countColumn)
            ->orderByDesc('count_all_time')
            ->limit($dto->limit)
            ->get()
            ->toArray();

        return [
            'rows'             => $rows,
            'window_used'      => $currentWindow,
            'fallback_applied' => $fallbackIndex > 0,
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Recompute (لم يتغير جوهرياً، أضفنا فقط division by zero protection)
    // ─────────────────────────────────────────────────────────────────

    public function recompute(int $projectId, string $language): array
    {
        $start = microtime(true);

        $stats = DB::select("
            SELECT
                keyword,
                LOWER(TRIM(keyword))                                     AS normalized_keyword,
                language,
                SUM(CASE WHEN searched_at >= NOW() - INTERVAL 24 HOUR
                    THEN 1 ELSE 0 END)                                   AS count_24h,
                SUM(CASE WHEN searched_at >= NOW() - INTERVAL 7 DAY
                    THEN 1 ELSE 0 END)                                   AS count_7d,
                SUM(CASE WHEN searched_at >= NOW() - INTERVAL 30 DAY
                    THEN 1 ELSE 0 END)                                   AS count_30d,
                COUNT(*)                                                 AS count_all_time,
                MAX(searched_at)                                         AS last_searched_at
            FROM user_search_logs
            WHERE project_id = ?
              AND language    = ?
              AND keyword IS NOT NULL
              AND CHAR_LENGTH(TRIM(keyword)) >= 2
            GROUP BY keyword, language
        ", [$projectId, $language]);

        if (empty($stats)) {
            return ['processed' => 0, 'upserted' => 0, 'duration_ms' => 0];
        }

        $clickCounts = DB::table('search_suggestions')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->pluck('click_count', 'normalized_keyword')
            ->toArray();

        $rows = [];
        $now  = now()->toDateTimeString();

        foreach ($stats as $row) {
            $lastSearchedAt = $row->last_searched_at
                ? \Carbon\Carbon::parse($row->last_searched_at)
                : null;

            $clickCount = (int) ($clickCounts[$row->normalized_keyword] ?? 0);

            // ─── حماية من Division by Zero ───────────────────────────
            $trendingScore = PopularSearch::calculateTrendingScore(
                count24h:       max(0, (int) $row->count_24h),
                count7d:        max(0, (int) $row->count_7d),
                count30d:       max(0, (int) $row->count_30d),
                lastSearchedAt: $lastSearchedAt,
            );

            $alltimeScore = PopularSearch::calculateAlltimeScore(
                countAllTime:   max(0, (int) $row->count_all_time),
                clickCount:     max(0, $clickCount),
                lastSearchedAt: $lastSearchedAt,
            );

            // ─── تأكد أن الـ scores ليست NaN أو INF ─────────────────
            $trendingScore = is_finite($trendingScore) ? $trendingScore : 0.0;
            $alltimeScore  = is_finite($alltimeScore)  ? $alltimeScore  : 0.0;

            $rows[] = [
                'project_id'         => $projectId,
                'keyword'            => $row->keyword,
                'language'           => $row->language,
                'normalized_keyword' => $row->normalized_keyword,
                'count_24h'          => (int) $row->count_24h,
                'count_7d'           => (int) $row->count_7d,
                'count_30d'          => (int) $row->count_30d,
                'count_all_time'     => (int) $row->count_all_time,
                'click_count'        => $clickCount,
                'trending_score'     => $trendingScore,
                'alltime_score'      => $alltimeScore,
                'last_searched_at'   => $row->last_searched_at,
                'last_computed_at'   => $now,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            PopularSearch::upsert(
                $chunk,
                ['project_id', 'normalized_keyword', 'language'],
                [
                    'count_24h', 'count_7d', 'count_30d', 'count_all_time',
                    'click_count', 'trending_score', 'alltime_score',
                    'last_searched_at', 'last_computed_at', 'updated_at',
                ]
            );
        }

        return [
            'processed'   => count($stats),
            'upserted'    => count($rows),
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }
}