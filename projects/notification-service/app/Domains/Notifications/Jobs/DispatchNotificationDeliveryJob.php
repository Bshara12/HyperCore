<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationChannel;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchNotificationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasNotificationJobMiddleware;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $deliveryId) {}

    protected function overlapKey(): string
    {
        return 'delivery:'.$this->deliveryId;
    }

    public function handle(): void
    {
        $delivery = NotificationDelivery::query()->findOrFail($this->deliveryId);

        if ($delivery->status === DeliveryStatus::Delivered) {
            return;
        }

        match (NotificationChannel::from($delivery->channel)) {
            NotificationChannel::Database => $this->handleDatabase($delivery),
            NotificationChannel::Broadcast => BroadcastNotificationJob::dispatch($delivery->id),
            NotificationChannel::Email => DispatchEmailNotificationJob::dispatch($delivery->id),
            NotificationChannel::Webhook => DispatchWebhookNotificationJob::dispatch($delivery->id),
        };
    }

    private function handleDatabase(NotificationDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Delivered,
            'sent_at' => now(),
            'delivered_at' => now(),
            'last_attempt_at' => now(),
            'attempts' => $delivery->attempts + 1,
        ])->save();

        $delivery->notification?->forceFill([
            'status' => NotificationStatus::Delivered,
            'delivered_at' => now(),
        ])->save();
    }
}
