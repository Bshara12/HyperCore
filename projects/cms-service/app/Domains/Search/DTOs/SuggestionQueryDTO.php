<?php

namespace App\Domains\Search\DTOs;

class SuggestionQueryDTO
{
    public function __construct(
        public readonly string $prefix,       // "lar" → ما يكتبه المستخدم
        public readonly int $projectId,
        public readonly string $language,
        public readonly int $limit,        // max 10
        public readonly ?int $userId,       // لـ personalization اختياري
    ) {}
}
