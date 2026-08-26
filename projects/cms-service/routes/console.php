<?php

use App\Jobs\RebuildSearchCorpusStatsJob;
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

/*
| احصاءات المتن - مقام IDF في BM25.
|
| تُجدول بعد اعادة الفهرسة لا قبلها: حسابها يقرأ الفهرس، فحسابها على
| فهرس قديم يعني ترتيباً مبنياً على احصاءات لا تصف المحتوى الحالي.
|
| والساعة الفاصلة هامش لا موعد: اعادة الفهرسة تتفاوت مدّتها بحجم
| المحتوى، و withoutOverlapping يحمي من التداخل ان طالت.
*/
app()->booted(function () {
    app(Schedule::class)
        ->call(function () {
            RebuildSearchCorpusStatsJob::dispatch()
                ->onQueue('search-maintenance');
        })
        ->name('rebuild-search-corpus-stats')
        ->dailyAt('03:00')
        ->withoutOverlapping();
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
// Usage reset runs once per hour. It used to be registered twice — as a
// closure and again as the console command — which reset every usage row twice.
app()->booted(function () {

    app(Schedule::class)
        ->command(
            'subscriptions:reset-usages'
        )
        ->name('reset-subscription-usages')
        ->hourly()
        ->withoutOverlapping()
        ->runInBackground();
});
