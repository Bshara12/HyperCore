<?php

namespace App\Domains\Subscription\DTOs\Usage;

class ProcessUsageEventDTO
{
    public function __construct(

        public readonly int $userId,

        public readonly ?int $projectId,

        public readonly string $eventKey,

        public readonly int $amount = 1
    ) {}
}