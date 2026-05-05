<?php

namespace App\Domains\Search\Services;

use App\Domains\Search\Actions\AnalyzeSynonymsAction;
use App\Domains\Search\DTOs\SynonymAnalysisResultDTO;
use App\Domains\Search\Repositories\Interfaces\SynonymSuggestionRepositoryInterface;
use App\Domains\Search\Support\SynonymExpander;
use Illuminate\Support\Facades\DB;

class SynonymAnalysisService
{
    public function __construct(
        private AnalyzeSynonymsAction $analyzeAction,
        private SynonymSuggestionRepositoryInterface $repository,
        private SynonymExpander $synonymExpander,
    ) {}

    public function analyze(
        int $projectId,
        string $language = 'en',
        int $days = 90,
        int $minCount = 2,
    ): SynonymAnalysisResultDTO {
        return $this->analyzeAction->execute($projectId, $language, $days, $minCount);
    }

    public function getPendingSuggestions(
        int $projectId,
        string $language = 'en',
        int $limit = 50,
        float $minConfidence = 0.3,
    ): array {
        return $this->repository->getPendingSuggestions(
            $projectId, $language, $limit, $minConfidence
        );
    }

    // public function reviewSuggestion(
    //     int     $suggestionId,
    //     string  $status,
    //     ?string $notes      = null,
    //     ?int    $reviewerId = null,
    // ): void {
    //     $validStatuses = ['approved', 'rejected', 'merged'];

    //     if (!in_array($status, $validStatuses, true)) {
    //         throw new \InvalidArgumentException("Invalid status: {$status}");
    //     }

    //     $this->repository->updateStatus($suggestionId, $status, $notes, $reviewerId);
    // }
    public function reviewSuggestion(
        int $suggestionId,
        string $status,
        ?string $notes = null,
        ?int $reviewerId = null,
    ): void {
        $validStatuses = ['approved', 'rejected', 'merged'];

        if (! in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        // ─── جلب معلومات السجل قبل التحديث ───────────────────────────
        $suggestion = DB::table('synonym_suggestions')
            ->where('id', $suggestionId)
            ->first(['project_id', 'language']);

        $this->repository->updateStatus($suggestionId, $status, $notes, $reviewerId);

        // ─── مسح الـ cache إذا تغيرت الحالة إلى approved/rejected ────
        if ($suggestion && in_array($status, ['approved', 'rejected'], true)) {
            $this->synonymExpander->invalidateCache(
                $suggestion->project_id,
                $suggestion->language
            );
        }
    }
}
