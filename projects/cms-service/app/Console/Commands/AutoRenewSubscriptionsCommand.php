<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domains\Subscription\Actions\Subscription\AutoRenewSubscriptionsAction;

class AutoRenewSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:auto-renew';

    protected $description =
        'Automatically renew subscriptions';

    public function __construct(
        private AutoRenewSubscriptionsAction $action
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->action->execute();

        $this->info(
            'Subscriptions processed successfully.'
        );
    }
}