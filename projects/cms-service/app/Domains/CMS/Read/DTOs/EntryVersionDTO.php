<?php

namespace App\Domains\CMS\Read\DTOs;

class EntryVersionDTO
{
    public function __construct(
        public int $id,
        public int $data_entry_id,
        public int $version_number,
        public ?int $created_by,
        public string $created_at,
        public ?array $snapshot = null
    ) {}
}
