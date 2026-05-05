<?php

namespace App\Domains\CMS\Read\DTOs;

class EntryDetailDTO
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $status,
        public array $values,
        public array $seo
    ) {}
}
