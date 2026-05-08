<?php

namespace Tests\Unit\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchNotificationDeliveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_database_delivery_as_delivered(): void
    {
        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Test',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'database',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $job = new DispatchNotificationDeliveryJob($delivery->id);
        $job->handle();

        $this->assertSame(DeliveryStatus::Delivered->value, $delivery->fresh()->status->value);
        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status->value);
    }

    public function test_it_dispatches_broadcast_job_for_broadcast_channel(): void
    {
        Bus::fake();

        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Test',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'broadcast',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $job = new DispatchNotificationDeliveryJob($delivery->id);
        $job->handle();

        Bus::assertDispatched(BroadcastNotificationJob::class, function ($queuedJob) use ($delivery) {
            return $queuedJob->deliveryId === $delivery->id;
        });
    }
}
