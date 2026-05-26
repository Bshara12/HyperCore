<?php

namespace App\Console\Commands;

use App\Domains\Notifications\Jobs\DispatchDueScheduledNotificationsJob;
use Illuminate\Console\Command;

class DispatchScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:dispatch-scheduled';

    protected $description = 'Dispatch all due scheduled notifications';

    public function handle(): int
    {
        DispatchDueScheduledNotificationsJob::dispatch();

        $this->info('Scheduled notifications dispatch job queued.');

        return self::SUCCESS;
    }
}
