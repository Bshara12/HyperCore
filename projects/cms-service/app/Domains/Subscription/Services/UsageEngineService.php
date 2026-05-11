<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Usage\ProcessUsageEventAction;
use App\Domains\Subscription\DTOs\Usage\ProcessUsageEventDTO;

class UsageEngineService
{
    public function __construct(
        private ProcessUsageEventAction $action
    ) {}

    public function handle(
        ProcessUsageEventDTO $dto
    ): void {

        $this->action
            ->execute($dto);
    }
}