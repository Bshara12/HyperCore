<?php

declare(strict_types=1);

namespace App\Domains\Search\DTOs;

/**
 * SearchQueryDTO — immutable
 *
 * $debug: عند true يُفعّل جمع trace metadata داخل SearchEntriesAction
 * صفر تأثير على production (default = false)
 */
final class SearchQueryDTO
{
    public function __construct(
        public readonly string  $keyword,
        public readonly int     $projectId,
        public readonly string  $language,
        public readonly int     $page,
        public readonly int     $perPage,
        public readonly ?string $dataTypeSlug = null,
        public readonly ?int    $userId       = null,
        public readonly ?string $sessionId    = null,
        public readonly bool    $debug        = false,  // ← جديد
    ) {}
}