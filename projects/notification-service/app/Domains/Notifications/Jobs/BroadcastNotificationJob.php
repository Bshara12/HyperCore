<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Events\NotificationCreated;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class BroadcastNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
  use HasNotificationJobMiddleware;

  public int $tries = 3;

  public int $timeout = 30;

  public function __construct(public string $deliveryId) {}

  protected function overlapKey(): string
  {
    return 'broadcast:' . $this->deliveryId;
  }

  public function handle(NotificationDeliveryService $deliveryService): void
  {
    $delivery = NotificationDelivery::query()
      ->with('notification')
      ->findOrFail($this->deliveryId);

    if ($delivery->status === DeliveryStatus::Delivered) {
      return;
    }

    try {
      $deliveryService->markQueued($delivery);

      // event(new NotificationCreated($delivery->notification));
      $notification = $delivery->notification;

      if (! $notification) {
        throw new \RuntimeException('Notification relation is missing.');
      }

      event(new NotificationCreated($notification));

      $deliveryService->markSent($delivery);
      $deliveryService->markDelivered($delivery);
    } catch (Throwable $e) {
      $deliveryService->markFailed(
        delivery: $delivery,
        code: class_basename($e),
        message: $e->getMessage(),
        backoffMinutes: 5 * max(1, $delivery->attempts + 1)
      );

      throw $e;
    }
  }
}
