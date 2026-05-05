<?php

namespace App\Domains\Search\Repositories\Interfaces;

use App\Domains\Search\DTOs\SynonymSuggestionDTO;

interface SynonymSuggestionRepositoryInterface
{
    /**
     * جلب keywords من user_search_logs للتحليل
     *
     * @return string[]
     */
    public function fetchKeywordsForAnalysis(
        int $projectId,
        string $language,
        int $days,
        int $minCount
    ): array;

    /**
     * حفظ مقترحات المرادفات (bulk upsert)
     *
     * @param  SynonymSuggestionDTO[]  $suggestions
     */
    public function saveSuggestions(int $projectId, array $suggestions): int;

    /**
     * جلب المقترحات للمراجعة
     *
     * @return object[]
     */
    public function getPendingSuggestions(
        int $projectId,
        string $language,
        int $limit,
        float $minConfidence
    ): array;

    /**
     * تحديث حالة مقترح
     */
    public function updateStatus(
        int $suggestionId,
        string $status,
        ?string $notes,
        ?int $reviewerId
    ): void;
}
