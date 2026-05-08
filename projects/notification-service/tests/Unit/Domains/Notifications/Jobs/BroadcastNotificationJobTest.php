<?php

namespace Tests\Unit\Domains\Notifications\Jobs;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Events\NotificationCreated;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_broadcasts_and_updates_delivery_state(): void
    {
        Event::fake();

        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Broadcast me',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'channel' => 'broadcast',
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [
                'title' => 'Broadcast me',
                'body' => 'Body',
            ],
        ]);

        $job = new BroadcastNotificationJob($delivery->id);
        $job->handle(app(NotificationDeliveryService::class));

        Event::assertDispatched(NotificationCreated::class);

        $this->assertSame(DeliveryStatus::Delivered->value, $delivery->fresh()->status->value);
        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status->value);
    }
}
