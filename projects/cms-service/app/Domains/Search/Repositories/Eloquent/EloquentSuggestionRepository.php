<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\DTOs\SuggestionQueryDTO;
use App\Models\SearchSuggestion;
use App\Domains\Search\Repositories\Interfaces\SuggestionRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EloquentSuggestionRepository implements SuggestionRepositoryInterface
{
    public function findByPrefix(SuggestionQueryDTO $dto): array
    {
        /*
         * استراتيجية الـ Query:
         *
         * 1. LIKE 'prefix%'   ← prefix matching فقط (سريع جداً مع index)
         * 2. ORDER BY score   ← الأكثر بحثاً والأحدث أولاً
         * 3. LIMIT            ← max 10 نتائج
         *
         * لماذا LIKE 'prefix%' وليس LIKE '%prefix%'؟
         * - 'prefix%' يستخدم الـ B-tree index → O(log n)
         * - '%prefix%' يُجري full table scan → O(n)
         * الفرق في الأداء ضخم عند البيانات الكبيرة
         */
        $normalizedPrefix = $this->normalize($dto->prefix);

        $rows = DB::table('search_suggestions')
            ->select('keyword', 'search_count', 'click_count', 'score')
            ->where('project_id', $dto->projectId)
            ->where('language', $dto->language)
            ->where('normalized_keyword', 'LIKE', $normalizedPrefix . '%')
            ->orderByDesc('score')
            ->orderByDesc('search_count')
            ->limit($dto->limit)
            ->get();

        return $rows->toArray();
    }

    // ─────────────────────────────────────────────────────────────────

    public function upsertFromSearch(
        int    $projectId,
        string $keyword,
        string $language
    ): void {
        $normalized  = $this->normalize($keyword);
        $now         = now();

        /*
         * INSERT ... ON DUPLICATE KEY UPDATE
         * أسرع بكثير من updateOrCreate() في Laravel
         * لأنه query واحد بدل query للبحث + query للتحديث
         *
         * يستخدم الـ unique index على (project_id, normalized_keyword, language)
         */
        DB::statement("
            INSERT INTO search_suggestions
                (project_id, keyword, language, normalized_keyword,
                 search_count, click_count, score, last_searched_at,
                 created_at, updated_at)
            VALUES
                (?, ?, ?, ?, 1, 0, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                search_count     = search_count + 1,
                last_searched_at = VALUES(last_searched_at),
                score            = ?,
                updated_at       = VALUES(updated_at)
        ", [
            // INSERT values
            $projectId,
            $keyword,
            $language,
            $normalized,
            SearchSuggestion::calculateScore(1, 0, $now),
            $now,
            $now,
            $now,
            // ON DUPLICATE KEY UPDATE score
            // نحسب الـ score الجديد بناءً على القيم المحدَّثة
            DB::raw("(
                LOG10(search_count + 2) +
                LOG10(click_count + 1) * 2.0 +
                GREATEST(0, 1.0 - (DATEDIFF(NOW(), last_searched_at) / 30))
            )"),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────

    public function incrementClickCount(
        int    $projectId,
        string $keyword,
        string $language
    ): void {
        $normalized = $this->normalize($keyword);

        DB::table('search_suggestions')
            ->where('project_id', $projectId)
            ->where('normalized_keyword', $normalized)
            ->where('language', $language)
            ->update([
                'click_count' => DB::raw('click_count + 1'),
                'score'       => DB::raw("(
                    LOG10(search_count + 1) +
                    LOG10(click_count + 2) * 2.0 +
                    GREATEST(0, 1.0 - (DATEDIFF(NOW(), last_searched_at) / 30))
                )"),
                'updated_at'  => now(),
            ]);
    }

    // ─────────────────────────────────────────────────────────────────

    public function buildFromSearchLogs(int $projectId): array
    {
        $stats = ['processed' => 0, 'upserted' => 0];

        /*
         * نجمع الكلمات من user_search_logs مرتبة حسب التكرار
         * ونُدرجها في search_suggestions بشكل bulk
         *
         * INSERT INTO search_suggestions (...)
         * SELECT ... FROM user_search_logs
         * GROUP BY keyword, language
         * ON DUPLICATE KEY UPDATE ...
         */
        $inserted = DB::statement("
            INSERT INTO search_suggestions
                (project_id, keyword, language, normalized_keyword,
                 search_count, click_count, score, last_searched_at,
                 created_at, updated_at)
            SELECT
                project_id,
                keyword,
                language,
                LOWER(TRIM(keyword)) as normalized_keyword,
                COUNT(*) as search_count,
                0 as click_count,
                LOG10(COUNT(*) + 1) as score,
                MAX(searched_at) as last_searched_at,
                NOW() as created_at,
                NOW() as updated_at
            FROM user_search_logs
            WHERE project_id = ?
              AND keyword IS NOT NULL
              AND CHAR_LENGTH(TRIM(keyword)) >= 2
            GROUP BY project_id, keyword, language
            ON DUPLICATE KEY UPDATE
                search_count     = VALUES(search_count),
                last_searched_at = VALUES(last_searched_at),
                score            = VALUES(score),
                updated_at       = NOW()
        ", [$projectId]);

        $stats['upserted'] = DB::table('search_suggestions')
            ->where('project_id', $projectId)
            ->count();

        $stats['processed'] = DB::table('user_search_logs')
            ->where('project_id', $projectId)
            ->count();

        return $stats;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * تطبيع الكلمة للمقارنة
     * lowercase + trim + إزالة مسافات متعددة
     */
    private function normalize(string $keyword): string
    {
        return mb_strtolower(
            trim(preg_replace('/\s+/', ' ', $keyword)),
            'UTF-8'
        );
    }
}