<?php

namespace App\Console\Commands;

use App\Domains\Subscription\Services\UsageResetService;
use Illuminate\Console\Command;

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
