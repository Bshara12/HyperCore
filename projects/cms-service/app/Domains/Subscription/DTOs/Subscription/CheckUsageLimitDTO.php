<?php

namespace App\Domains\Subscription\DTOs\Usage;

class CheckUsageLimitDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $projectId,
        public readonly string $featureKey,
        public readonly int $requestedAmount = 1
    ) {}
}