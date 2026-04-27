<?php

namespace App\Domains\Search\DTOs;

class PopularSearchQueryDTO
{
    /**
     * @param string $window  '24h' | '7d' | '30d' | 'all'
     * @param string $type    'trending' | 'popular' | 'both'
     */
    public function __construct(
        public readonly int    $projectId,
        public readonly string $language,
        public readonly string $window,
        public readonly string $type,
        public readonly int    $limit,
    ) {}
}