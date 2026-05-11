<?php

namespace App\Providers;

use App\Events\SystemLogEvent;
use App\Events\UserLoggedIn;
use App\Listeners\PublishLoginLog;
use App\Listeners\PublishSystemLog;
use Illuminate\Support\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    protected $listen = [
        UserLoggedIn::class => [
            PublishLoginLog::class,
        ],
        SystemLogEvent::class => [
            PublishSystemLog::class,
        ],
    ];

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
