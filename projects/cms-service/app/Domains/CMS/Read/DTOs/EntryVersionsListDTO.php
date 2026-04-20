<?php

namespace App\Domains\CMS\Read\DTOs;

class EntryVersionsListDTO
{
    /** @param EntryVersionDTO[] $items */
    public function __construct(
        public int $total,
        public int $page,
        public int $per_page,
        public array $items,
    ) {}
}
