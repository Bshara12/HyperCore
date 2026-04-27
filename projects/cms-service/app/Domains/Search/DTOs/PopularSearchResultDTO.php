<?php

namespace App\Domains\Search\DTOs;

class PopularSearchResultDTO
{
    /**
     * @param PopularSearchItemDTO[] $trending
     * @param PopularSearchItemDTO[] $popular
     */
    public function __construct(
        public readonly array  $trending,
        public readonly array  $popular,
        public readonly string $window,
        public readonly string $actualWindowUsed,    // ← جديد: الـ window الفعلي المستخدم
        public readonly bool   $fallbackApplied,     // ← جديد: هل طُبِّق fallback؟
        public readonly string $source,
        public readonly float  $tookMs,
    ) {}

    public function toArray(): array
    {
        return [
            'window'              => $this->window,
            'actual_window_used'  => $this->actualWindowUsed,
            'fallback_applied'    => $this->fallbackApplied,
            'source'              => $this->source,
            'took_ms'             => round($this->tookMs, 2),
            'trending'            => array_map(fn($i) => $i->toArray(), $this->trending),
            'popular'             => array_map(fn($i) => $i->toArray(), $this->popular),
        ];
    }
}