<?php

namespace App\Domains\Search\DTOs;

class UserPreferenceDTO
{
    /**

     * @param  array<int, float>     $affinities      [data_type_id => affinity]
     *                                                 إشارة ثانوية ضعيفة (secondary/weak signal).
     * @param  array<string, float>  $termAffinities  [normalized_term => affinity]
     *                                                 الإشارة الأساسية للشخصنة (primary signal)،
     *                                                 مبنية من نفس النص المفهرَس فعلياً في محرك
     *                                                 البحث (title+content)، محدودة بأعلى VOCAB_CAP
     *                                                 كلمة (انظر UserPreferenceAnalyzer).
     */
    public function __construct(
        public readonly array $affinities,
        public readonly array $termAffinities,
        public readonly int $totalClicks,
        public readonly bool $hasHistory,
    ) {}

    public static function noHistory(): self
    {
        return new self(
            affinities: [],

            termAffinities: [],
            totalClicks: 0,
            hasHistory: false,
        );
    }


    public function affinityFor(int $dataTypeId): float
    {
        return $this->affinities[$dataTypeId] ?? 0.0;
    }


    public function termAffinityFor(string $term): float
    {
        return $this->termAffinities[$term] ?? 0.0;
    }

    public function topAffinities(int $limit = 3): array
    {
        if (empty($this->affinities)) {
            return [];
        }

        $sorted = $this->affinities;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }


    public function topTerms(int $limit = 5): array
    {
        if (empty($this->termAffinities)) {
            return [];
        }

        $sorted = $this->termAffinities;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }
}