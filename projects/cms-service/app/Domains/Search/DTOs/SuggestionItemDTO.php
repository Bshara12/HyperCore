<?php

namespace App\Domains\Search\DTOs;

class SuggestionItemDTO
{
    public function __construct(
        public readonly string $keyword,
        public readonly int    $searchCount,
        public readonly float  $score,
        public readonly string $highlight,  // "**lar**avel" للـ UI
    ) {}

    public function toArray(): array
    {
        return [
            'keyword'      => $this->keyword,
            'search_count' => $this->searchCount,
            'score'        => $this->score,
            'highlight'    => $this->highlight,
        ];
    }
}