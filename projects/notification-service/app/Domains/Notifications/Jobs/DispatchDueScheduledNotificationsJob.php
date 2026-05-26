<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Database\Eloquent\Collection;

class DispatchDueScheduledNotificationsJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  use HasNotificationJobMiddleware;

  public int $timeout = 60;

  protected function overlapKey(): string
  {
    return 'scheduled-notifications:sweep';
  }

  protected function overlapReleaseAfter(): int
  {
    return 30;
  }

  protected function overlapExpireAfter(): int
  {
    return 180;
  }

  public function handle(): void
  {
    Notification::query()
      ->with('deliveries')
      ->where('status', NotificationStatus::Pending)
      ->whereNotNull('scheduled_at')
      ->where('scheduled_at', '<=', now())
      // ->chunkById(100, function ($notifications) {
      //   foreach ($notifications as $notification) {
      //     $notification->forceFill([
      //       'status' => NotificationStatus::Queued,
      //       'queued_at' => now(),
      //     ])->save();

      //     foreach ($notification->deliveries as $delivery) {
      //       if ($delivery->channel === 'database') {
      //         continue;
      //       }

      //       DispatchNotificationDeliveryJob::dispatch($delivery->id);
      //     }
      //   }
      // });
      ->chunkById(100, function (Collection $notifications) {
        foreach ($notifications as $notification) {

          /** @var Notification $notification */

          $notification->forceFill([
            'status' => NotificationStatus::Queued,
            'queued_at' => now(),
          ])->save();

          foreach ($notification->deliveries as $delivery) {

            /** @var NotificationDelivery $delivery */

            if ($delivery->channel === 'database') {
              continue;
            }

            DispatchNotificationDeliveryJob::dispatch($delivery->id);
          }
        }
      });
  }
}
