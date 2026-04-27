<?php

namespace App\Domains\Search\DTOs;

class SearchQueryDTO
{
    public function __construct(
        public readonly string  $keyword,
        public readonly int     $projectId,
        public readonly string  $language,
        public readonly int     $page,
        public readonly int     $perPage,
        public readonly ?string $dataTypeSlug   = null,
        public readonly ?int    $userId         = null,   // ← إضافة
        public readonly ?string $sessionId      = null,   // ← إضافة
    ) {}
}