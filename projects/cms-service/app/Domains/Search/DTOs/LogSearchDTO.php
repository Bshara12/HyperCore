<?php

namespace App\Domains\Search\DTOs;

class LogSearchDTO
{
    public function __construct(
        public readonly int     $projectId,
        public readonly string  $keyword,
        public readonly string  $language,
        public readonly int     $resultsCount,
        public readonly ?string $detectedIntent,
        public readonly ?float  $intentConfidence,
        public readonly ?int    $userId       = null,
        public readonly ?string $sessionId    = null,
    ) {}
}