<?php

namespace App\Domains\Search\DTOs;

class LogClickDTO
{
    public function __construct(
        public readonly int $projectId,
        public readonly int $entryId,
        public readonly int $dataTypeId,
        public readonly int $resultPosition,
        public readonly ?int $userId = null,
        public readonly ?int $searchLogId = null,
        public readonly ?string $sessionId = null,
    ) {}
}
