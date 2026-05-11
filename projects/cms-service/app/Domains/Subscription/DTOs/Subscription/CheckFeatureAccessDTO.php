<?php

namespace App\Domains\Subscription\DTOs\Feature;

class CheckFeatureAccessDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $projectId,
        public readonly string $featureKey
    ) {}
}