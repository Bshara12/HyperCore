<?php

namespace App\Domains\Notifications\Channels;

use App\Domains\Notifications\Contracts\NotificationChannelDriver;
use App\Domains\Notifications\Mail\NotificationMail;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChannelDriver implements NotificationChannelDriver
{
    public function __construct(
        private readonly NotificationDeliveryService $deliveryService
    ) {}

    public function send(NotificationDelivery $delivery): void
    {
        $notification = $delivery->notification;

        $email = data_get($notification->metadata, 'email.to');

        if (! $email) {
            $this->deliveryService->markSkipped($delivery, 'Recipient email is missing.');
            return;
        }

        try {
            $this->deliveryService->markQueued($delivery);

            Mail::to($email)->send(new NotificationMail($notification));

            $this->deliveryService->markSent($delivery);
            $this->deliveryService->markDelivered($delivery);
        } catch (Throwable $e) {
            $this->deliveryService->markFailed(
                delivery: $delivery,
                code: class_basename($e),
                message: $e->getMessage(),
                backoffMinutes: 5 * max(1, $delivery->attempts + 1)
            );

            throw $e;
        }
    }
}
