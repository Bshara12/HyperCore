<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RetryFailedNotificationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasNotificationJobMiddleware;

    public int $timeout = 30;

    protected function overlapKey(): string
    {
        return 'delivery-retry:sweep';
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
        NotificationDelivery::query()
            ->where('status', DeliveryStatus::Failed)
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->chunkById(100, function ($deliveries) {
                foreach ($deliveries as $delivery) {
                    if ($delivery->attempts >= $delivery->max_attempts) {
                        continue;
                    }

                    DispatchNotificationDeliveryJob::dispatch($delivery->id);
                }
            });
    }
}
