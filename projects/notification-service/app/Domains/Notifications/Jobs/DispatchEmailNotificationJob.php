<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Channels\EmailChannelDriver;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchEmailNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasNotificationJobMiddleware;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public string $deliveryId) {}

    protected function overlapKey(): string
    {
        return 'email:'.$this->deliveryId;
    }

    public function handle(EmailChannelDriver $driver): void
    {
        $delivery = NotificationDelivery::query()
            ->with('notification')
            ->findOrFail($this->deliveryId);

        $driver->send($delivery);
    }
}
