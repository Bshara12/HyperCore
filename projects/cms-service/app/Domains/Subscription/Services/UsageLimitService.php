<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Usage\CheckUsageLimitAction;
use App\Domains\Subscription\Actions\Usage\IncrementUsageAction;
use App\Domains\Subscription\DTOs\Usage\CheckUsageLimitDTO;

class UsageLimitService
{
    public function __construct(
        private CheckUsageLimitAction $checkAction,
        private IncrementUsageAction $incrementAction
    ) {}

    public function hasRemaining(
        CheckUsageLimitDTO $dto
    ): bool {

        return $this->checkAction
            ->execute($dto);
    }

    public function increment(
        CheckUsageLimitDTO $dto
    ): void {

        $this->incrementAction
            ->execute($dto);
    }
}