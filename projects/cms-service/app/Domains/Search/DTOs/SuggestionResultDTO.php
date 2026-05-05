<?php

namespace App\Domains\Search\DTOs;

class SuggestionResultDTO
{
    /**
     * @param  SuggestionItemDTO[]  $items
     */
    public function __construct(
        public readonly string $prefix,
        public readonly array $items,
        public readonly string $source,    // 'cache' | 'db'
        public readonly float $tookMs,
    ) {}

    public function toArray(): array
    {
        return [
            'prefix' => $this->prefix,
            'took_ms' => round($this->tookMs, 2),
            'source' => $this->source,
            'results' => array_map(fn ($i) => $i->toArray(), $this->items),
        ];
    }
}
