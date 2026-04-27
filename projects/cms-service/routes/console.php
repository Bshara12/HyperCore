<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
  $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule::command('search:reindex --force')
//   ->dailyAt('02:00')
//   ->withoutOverlapping()
//   ->runInBackground()
//   ->appendOutputTo(storage_path('logs/search-reindex.log'));

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
