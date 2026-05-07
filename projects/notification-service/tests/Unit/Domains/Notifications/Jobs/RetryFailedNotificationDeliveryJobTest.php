<?php

namespace Test\Unit\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Jobs\RetryFailedNotificationDeliveryJob;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class RetryFailedNotificationDeliveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_retry_for_due_failed_deliveries(): void
    {
        Bus::fake();

        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Retry',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'broadcast',
            'status' => DeliveryStatus::Failed,
            'attempts' => 1,
            'max_attempts' => 3,
            'next_retry_at' => now()->subMinute(),
            'payload_snapshot' => [],
        ]);

        $job = new RetryFailedNotificationDeliveryJob();
        $job->handle();

        Bus::assertDispatched(DispatchNotificationDeliveryJob::class, function ($queuedJob) use ($delivery) {
            return $queuedJob->deliveryId === $delivery->id;
        });
    }
}
