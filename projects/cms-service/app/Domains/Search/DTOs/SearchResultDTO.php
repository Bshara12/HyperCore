<?php

namespace App\Domains\Search\DTOs;

class SearchResultDTO
{
    /**
     * @param SearchResultItemDTO[] $items
     */
    public function __construct(
        public readonly string $keyword,
        public readonly int    $total,
        public readonly int    $page,
        public readonly int    $perPage,
        public readonly int    $lastPage,
        public readonly array  $items,
    ) {}

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'meta'    => [
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