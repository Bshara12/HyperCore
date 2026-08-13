<?php

namespace App\Domains\Search\DTOs;

class UserPreferenceDTO
{
    /**
     * @param  array<int, float>  $affinities  [data_type_id => affinity score بين 0.0 و 1.0]
     *                                          كل data_type_id له درجة مستقلة بذاته، بلا تنافس
     *                                          على مجموع = 1.0 (خلافاً لـ typeScores القديم).
     */
    public function __construct(
        public readonly array $affinities,
        public readonly int $totalClicks,
        public readonly bool $hasHistory,
    ) {}

    public static function noHistory(): self
    {
        return new self(
            affinities: [],
            totalClicks: 0,
            hasHistory: false,
        );
    }

    /**
     * درجة الـ affinity لنوع بيانات محدد. 0.0 إذا لا يوجد أي إشارة له.
     */
    public function affinityFor(int $dataTypeId): float
    {
        return $this->affinities[$dataTypeId] ?? 0.0;
    }

    /**
     * أعلى N أنواع بيانات تفضيلاً، مرتبة تنازلياً حسب الـ affinity.
     * تُستخدم في SearchCacheService::buildKey() لبناء مفتاح كاش مضغوط.
     *
     * @return array<int, float> [data_type_id => affinity]
     */
    public function topAffinities(int $limit = 3): array
    {
        if (empty($this->affinities)) {
            return [];
        }

        $sorted = $this->affinities;
        arsort($sorted);

        return array_slice($sorted, 0, $limit, true);
    }
}