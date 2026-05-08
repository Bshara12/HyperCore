<?php

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Notifications\Enums\NotificationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScheduledNotificationsHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_scheduled_notification(): void
    {
        Bus::fake();

        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => [
                'type' => 'user',
                'id' => 10,
            ],
            'source' => [
                'service' => 'cms',
                'type' => 'content_scheduled',
                'id' => 'evt_schedule_1',
            ],
            'title' => 'Scheduled',
            'body' => 'Will be sent later',
            'channel' => ['broadcast'],
            'scheduled_at' => now()->addMinute()->toISOString(),
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'title' => 'Scheduled',
            'status' => NotificationStatus::Pending->value,
        ]);
    }
}
