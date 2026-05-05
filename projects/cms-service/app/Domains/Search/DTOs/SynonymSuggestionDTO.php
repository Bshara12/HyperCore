<?php

namespace App\Domains\Search\DTOs;

class SynonymSuggestionDTO
{
    public function __construct(
        public readonly string $wordA,
        public readonly string $wordB,
        public readonly float $jaccardScore,
        public readonly int $cooccurrenceCount,
        public readonly float $confidenceScore,
        public readonly int $wordACount,
        public readonly int $wordBCount,
        public readonly string $language,
    ) {}

    /**
     * تطبيع الترتيب: word_a دائماً أبجدياً قبل word_b
     * لمنع التكرار: (php, laravel) و (laravel, php) = نفس السجل
     */
    public static function normalized(
        string $wordA,
        string $wordB,
        float $jaccardScore,
        int $cooccurrenceCount,
        float $confidenceScore,
        int $wordACount,
        int $wordBCount,
        string $language,
    ): self {
        if (strcmp($wordA, $wordB) > 0) {
            [$wordA, $wordB] = [$wordB, $wordA];
            [$wordACount, $wordBCount] = [$wordBCount, $wordACount];
        }

        return new self(
            wordA: $wordA,
            wordB: $wordB,
            jaccardScore: $jaccardScore,
            cooccurrenceCount: $cooccurrenceCount,
            confidenceScore: $confidenceScore,
            wordACount: $wordACount,
            wordBCount: $wordBCount,
            language: $language,
        );
    }
}
