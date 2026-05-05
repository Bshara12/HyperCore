<?php

namespace App\Domains\Search\DTOs;

class SynonymAnalysisResultDTO
{
    /**
     * @param  SynonymSuggestionDTO[]  $suggestions
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $keywordsAnalyzed,
        public readonly int $uniqueWordsFound,
        public readonly int $pairsEvaluated,
        public readonly int $suggestionsGenerated,
        public readonly array $suggestions,
        public readonly float $durationMs,
    ) {}

    public function toArray(): array
    {
        return [
            'project_id' => $this->projectId,
            'keywords_analyzed' => $this->keywordsAnalyzed,
            'unique_words_found' => $this->uniqueWordsFound,
            'pairs_evaluated' => $this->pairsEvaluated,
            'suggestions_generated' => $this->suggestionsGenerated,
            'duration_ms' => round($this->durationMs, 2),
            'suggestions' => array_map(fn ($s) => [
                'word_a' => $s->wordA,
                'word_b' => $s->wordB,
                'jaccard_score' => round($s->jaccardScore, 4),
                'cooccurrence_count' => $s->cooccurrenceCount,
                'confidence_score' => round($s->confidenceScore, 4),
            ], $this->suggestions),
        ];
    }
}
