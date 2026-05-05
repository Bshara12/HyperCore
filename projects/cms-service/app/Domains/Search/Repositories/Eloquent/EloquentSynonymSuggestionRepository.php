<?php

namespace App\Domains\Search\Repositories\Eloquent;

use App\Domains\Search\Repositories\Interfaces\SynonymSuggestionRepositoryInterface;
use App\Models\SynonymSuggestion;
use Illuminate\Support\Facades\DB;

class EloquentSynonymSuggestionRepository implements SynonymSuggestionRepositoryInterface
{
    public function fetchKeywordsForAnalysis(
        int $projectId,
        string $language,
        int $days = 90,
        int $minCount = 2
    ): array {
        /*
         * نجلب الـ keywords الأكثر تكراراً فقط
         * لأن تحليل كل keyword مكلف حسابياً
         *
         * HAVING COUNT(*) >= minCount → نتجاهل keywords بحثت مرة واحدة فقط
         * لأنها لا تعطي إشارة موثوقة
         */
        $rows = DB::table('user_search_logs')
            ->select('keyword')
            ->selectRaw('COUNT(*) as freq')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->whereNotNull('keyword')
            ->whereRaw('CHAR_LENGTH(TRIM(keyword)) >= 3')
            ->where('searched_at', '>=', now()->subDays($days))
            ->groupBy('keyword')
            ->having('freq', '>=', $minCount)
            ->orderByDesc('freq')
            ->limit(5000)  // حد أقصى لتجنب الـ memory overflow
            ->pluck('keyword')
            ->toArray();

        return $rows;
    }

    // ─────────────────────────────────────────────────────────────────

    public function saveSuggestions(int $projectId, array $suggestions): int
    {
        if (empty($suggestions)) {
            return 0;
        }

        $now = now()->toDateTimeString();
        $rows = [];

        foreach ($suggestions as $s) {
            $rows[] = [
                'project_id' => $projectId,
                'word_a' => $s->wordA,
                'word_b' => $s->wordB,
                'language' => $s->language,
                'jaccard_score' => $s->jaccardScore,
                'cooccurrence_count' => $s->cooccurrenceCount,
                'confidence_score' => $s->confidenceScore,
                'word_a_count' => $s->wordACount,
                'word_b_count' => $s->wordBCount,
                'status' => 'pending',
                'last_computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // upsert: إذا وُجد الزوج مسبقاً، حدِّث scores فقط
        // لكن لا تُغير status إذا كان reviewed بالفعل
        $upserted = 0;

        foreach (array_chunk($rows, 100) as $chunk) {
            SynonymSuggestion::upsert(
                $chunk,
                ['project_id', 'word_a', 'word_b', 'language'],
                [
                    'jaccard_score',
                    'cooccurrence_count',
                    'confidence_score',
                    'word_a_count',
                    'word_b_count',
                    'last_computed_at',
                    'updated_at',
                ]
            );
            $upserted += count($chunk);
        }

        return $upserted;
    }

    // ─────────────────────────────────────────────────────────────────

    public function getPendingSuggestions(
        int $projectId,
        string $language = 'en',
        int $limit = 50,
        float $minConfidence = 0.3
    ): array {
        return DB::table('synonym_suggestions')
            ->where('project_id', $projectId)
            ->where('language', $language)
            ->where('status', 'pending')
            ->where('confidence_score', '>=', $minConfidence)
            ->orderByDesc('confidence_score')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────

    public function updateStatus(
        int $suggestionId,
        string $status,
        ?string $notes = null,
        ?int $reviewerId = null
    ): void {
        SynonymSuggestion::where('id', $suggestionId)->update([
            'status' => $status,
            'reviewer_notes' => $notes,
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
