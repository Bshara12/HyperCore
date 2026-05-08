<?php

namespace Tests\Unit\Domains\Notifications;

use App\Domains\Notifications\Enums\DeliveryStatus;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\NotificationDeliveryService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_delivery_as_delivered_and_updates_notification(): void
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
            'channel' => 'broadcast',
            'status' => DeliveryStatus::Queued,
            'attempts' => 0,
            'max_attempts' => 3,
            'payload_snapshot' => [],
        ]);

        $service = app(NotificationDeliveryService::class);

        $service->markDelivered($delivery);

        $this->assertSame(DeliveryStatus::Delivered->value, $delivery->fresh()->status->value);
        $this->assertSame(NotificationStatus::Delivered->value, $notification->fresh()->status->value);
    }
}
