<?php

namespace App\Support\Services;

use App\Domains\Subscription\Services\UsageEngineService;
use App\Domains\Subscription\DTOs\Usage\ProcessUsageEventDTO;

class DomainEventService
{
    public function __construct(
        private UsageEngineService $usageEngine
    ) {}

    public function dispatch(

        int $userId,

        ?int $projectId,

        string $eventKey,

        int $amount = 1
    ): void {

        $this->usageEngine->handle(

            new ProcessUsageEventDTO(

                userId: $userId,

                projectId: $projectId,

                eventKey: $eventKey,

                amount: $amount
            )
        );
    }
}