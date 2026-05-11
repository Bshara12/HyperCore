<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\Subscription\Services\UsageResetService;

class ResetSubscriptionUsagesCommand extends Command
{
    protected $signature =
        'subscriptions:reset-usages';

    protected $description =
        'Reset expired subscription usages';

    public function handle(
        UsageResetService $service
    ): void {

        $service->handle();

        $this->info(
            'Subscription usages reset successfully.'
        );
    }
}