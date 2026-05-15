<?php

declare(strict_types=1);

namespace App\Domains\Search\DTOs;

/**
 * SearchResultDTO — immutable
 *
 * debug_trace: optional metadata أُضيف فقط عند SearchQueryDTO::$debug = true
 * لا يُملأ في production requests — صفر overhead.
 */
final class SearchResultDTO
{
    /**
     * @param SearchResultItemDTO[] $items
     * @param array<string, mixed>  $debugTrace  — فارغ في production
     */
    public function __construct(
        public readonly string  $keyword,
        public readonly int     $total,
        public readonly int     $page,
        public readonly int     $perPage,
        public readonly int     $lastPage,
        public readonly array   $items,
        public readonly bool    $aiEnhanced    = false,
        public readonly ?string $aiQuery       = null,
        public readonly bool    $keyboardFixed = false,
        public readonly ?string $keyboardQuery = null,
        public readonly array   $debugTrace    = [],   // ← جديد، فارغ افتراضياً
    ) {}

    public function toArray(): array
    {
        return [
            'keyword'        => $this->keyword,
            'ai_enhanced'    => $this->aiEnhanced,
            'ai_query'       => $this->aiQuery,
            'keyboard_fixed' => $this->keyboardFixed,
            'keyboard_query' => $this->keyboardQuery,
            'meta'           => [
                'total'     => $this->total,
                'page'      => $this->page,
                'per_page'  => $this->perPage,
                'last_page' => $this->lastPage,
            ],
            'results' => array_map(
                fn(SearchResultItemDTO $item) => $item->toArray(),
                $this->items
            ),
        ];
    }
}