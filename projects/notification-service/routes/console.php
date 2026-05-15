<?php

use App\Domains\Notifications\Jobs\DispatchDueScheduledNotificationsJob;
use App\Domains\Notifications\Jobs\RetryFailedNotificationDeliveryJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new DispatchDueScheduledNotificationsJob)->everyMinute();
Schedule::job(new RetryFailedNotificationDeliveryJob)->everyFiveMinutes();

Schedule::command('notifications:prune')->daily();
Schedule::command('notifications:prune-failed', ['--days' => 14])->daily();
