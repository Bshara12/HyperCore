<?php

namespace App\Providers;

use App\Events\DataEntrySavedEvent;
use App\Events\EntryChanged;
use App\Events\EntryRemovedFromSearch;
use App\Events\SystemLogEvent;
use App\Events\UserLoggedIn;
use App\Listeners\CreateVersionListener;
use App\Listeners\IndexDataEntryListener;
use App\Listeners\PublishLoginLog;
use App\Listeners\PublishSystemLog;
use App\Listeners\RemoveEntryFromSearchListener;
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
    EntryChanged::class => [
      CreateVersionListener::class,
    ],
    UserLoggedIn::class => [
      PublishLoginLog::class,
    ],
    SystemLogEvent::class => [
      PublishSystemLog::class,
    ],
    DataEntrySavedEvent::class => [
      IndexDataEntryListener::class,
    ],
    EntryRemovedFromSearch::class => [
      RemoveEntryFromSearchListener::class,
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
