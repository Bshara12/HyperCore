<?php

namespace App\Domains\Subscription\Services;

use App\Domains\Subscription\Actions\Usage\ResetSubscriptionUsageAction;

class UsageResetService
{
    public function __construct(
        private ResetSubscriptionUsageAction $action
    ) {}

    public function handle(): void
    {
        $this->action->execute();
    }
}