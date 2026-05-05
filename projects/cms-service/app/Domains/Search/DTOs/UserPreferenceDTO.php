<?php

namespace App\Domains\Search\DTOs;

class UserPreferenceDTO
{
    public function __construct(
        public readonly string $preferredType,  // product | article | service | general
        public readonly float $confidence,     // 0.0 → 1.0
        public readonly array $typeScores,     // ['product' => 0.7, 'article' => 0.2, ...]
        public readonly int $totalClicks,    // إجمالي النقرات المحللة
        public readonly bool $hasHistory,     // هل يوجد تاريخ أصلاً؟
    ) {}

    public static function noHistory(): self
    {
        return new self(
            preferredType: 'general',
            confidence: 0.0,
            typeScores: [],
            totalClicks: 0,
            hasHistory: false,
        );
    }
}
