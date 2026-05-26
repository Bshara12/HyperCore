<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;

class NotificationDeliveryService
{
    public function markQueued(NotificationDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Queued,
            'last_attempt_at' => now(),
            'attempts' => $delivery->attempts + 1,
        ])->save();
    }

    public function markSent(NotificationDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Sent,
            'sent_at' => now(),
            'last_attempt_at' => now(),
            'attempts' => $delivery->attempts + 1,
        ])->save();
    }

    public function markDelivered(NotificationDelivery $delivery): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Delivered,
            'delivered_at' => now(),
            'last_attempt_at' => now(),
            'attempts' => $delivery->attempts + 1,
        ])->save();

        $this->updateNotificationLifecycle($delivery->notification);
    }

    public function markFailed(NotificationDelivery $delivery, ?string $code = null, ?string $message = null, int $backoffMinutes = 5): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'last_attempt_at' => now(),
            'attempts' => $delivery->attempts + 1,
            'next_retry_at' => $delivery->attempts + 1 < $delivery->max_attempts
                ? now()->addMinutes($backoffMinutes)
                : null,
        ])->save();

        $this->updateNotificationFailureState($delivery->notification);
    }

    public function markSkipped(NotificationDelivery $delivery, ?string $message = null): void
    {
        $delivery->forceFill([
            'status' => DeliveryStatus::Skipped,
            'error_message' => $message,
            'last_attempt_at' => now(),
        ])->save();
    }

    private function updateNotificationLifecycle(Notification $notification): void
    {
        $allDelivered = $notification->deliveries()
            ->whereNotIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Skipped])
            ->doesntExist();

        if ($allDelivered) {
            $notification->forceFill([
                'status' => NotificationStatus::Delivered,
                'delivered_at' => now(),
            ])->save();
        }
    }

    private function updateNotificationFailureState(Notification $notification): void
    {
        $allFailedOrDone = $notification->deliveries()
            ->whereNotIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Skipped])
            ->doesntExist();

        if ($allFailedOrDone) {
            $notification->forceFill([
                'status' => NotificationStatus::Failed,
            ])->save();
        }
    }
}
