<?php

namespace App\Providers;

use App\Events\UserLoggedIn;
use App\Listeners\PublishLoginLog;
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
    \App\Events\EntryChanged::class => [
      \App\Listeners\CreateVersionListener::class,
    ],
    UserLoggedIn::class => [
      PublishLoginLog::class,
    ],
    \App\Events\SystemLogEvent::class => [
      \App\Listeners\PublishSystemLog::class,
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
