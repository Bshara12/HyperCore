<?php

namespace App\Domains\Search\DTOs;

class PopularSearchItemDTO
{
    public function __construct(
        public readonly string $keyword,
        public readonly int $count,
        public readonly float $score,
        public readonly ?string $trend,
        public readonly string $windowUsed,    // ← جديد: من أي window جاءت هذه النتيجة
    ) {}

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'count' => $this->count,
            'score' => round($this->score, 4),
            'trend' => $this->trend,
            'window_used' => $this->windowUsed,
        ];
    }
}
