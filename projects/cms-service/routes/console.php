<?php

use App\Domains\Subscription\Services\UsageResetService;
use App\Jobs\UpdatePopularityScoreJob;
use App\Jobs\UpdateSearchSignalsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;


Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

app()->booted(function () {
  app(Schedule::class)
    ->command('search:reindex --force')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/search-reindex.log'));
});

app()->booted(function () {
  app(Schedule::class)
    ->command('search:recompute-popular --force')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/popular-searches.log'));
});

app()->booted(function () {
  app(Schedule::class)
    ->call(function () {
      UpdateSearchSignalsJob::dispatch()
        ->onQueue('search-maintenance');
    })
    ->name('update-search-signals')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/search-signals.log'));
});

app()->booted(function () {
  app(Schedule::class)
    ->call(function () {
      UpdatePopularityScoreJob::dispatch()
        ->onQueue('search-tracking');
    })
    ->name('update-popularity-score')
    ->hourly()
    ->withoutOverlapping();
});

app()->booted(function () {

  app(Schedule::class)

    ->command('subscriptions:auto-renew')

    ->everyMinute()

    ->withoutOverlapping()

    ->runInBackground()

    ->appendOutputTo(
      storage_path(
        'logs/subscription-auto-renew.log'
      )
    );
});
app()->booted(function () {

  app(Schedule::class)

    ->call(function () {

      app(UsageResetService::class)
        ->handle();
    })

    ->name('reset-subscription-usages')

    ->hourly()

    ->withoutOverlapping();
});

app()->booted(function () {

  app(Schedule::class)

    ->command(
      'subscriptions:reset-usages'
    )

    ->hourly()

    ->withoutOverlapping()

    ->runInBackground();
});
