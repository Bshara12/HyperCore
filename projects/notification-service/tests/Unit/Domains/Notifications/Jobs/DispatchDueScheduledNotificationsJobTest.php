<?php

namespace Tests\Unit\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\DispatchDueScheduledNotificationsJob;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchDueScheduledNotificationsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_queues_due_notifications_and_dispatches_deliveries(): void
    {
        Bus::fake();

        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Scheduled',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Pending,
            'scheduled_at' => now()->subMinute(),
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'broadcast',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $job = new DispatchDueScheduledNotificationsJob();
        $job->handle();

        $this->assertSame(NotificationStatus::Queued->value, $notification->fresh()->status->value);
        Bus::assertDispatched(DispatchNotificationDeliveryJob::class, function ($queuedJob) use ($delivery) {
            return $queuedJob->deliveryId === $delivery->id;
        });
    }
}
