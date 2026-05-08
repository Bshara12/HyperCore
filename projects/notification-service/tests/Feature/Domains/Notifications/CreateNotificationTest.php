<?php

namespace App\s\Feature\Domains\Notifications;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_notification_and_deliveries(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'project_id' => 1,
            'recipient' => [
                'type' => 'user',
                'id' => 10,
            ],
            'source' => [
                'service' => 'cms',
                'type' => 'content_published',
                'id' => 'evt_1',
            ],
            'title' => 'Hello',
            'body' => 'World',
            'channel' => ['database', 'broadcast'],
            'dedupe_key' => 'cms:content_published:evt_1:user:10',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'title' => 'Hello',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'database',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'broadcast',
        ]);
    }

    public function test_it_respects_dedupe_key(): void
    {
        Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'content_published',
            'source_service' => 'cms',
            'source_id' => 'evt_1',
            'created_by_type' => 'user',
            'created_by_id' => '10',
            'title' => 'Existing',
            'body' => 'Existing',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
            'dedupe_key' => 'dup-key',
        ]);

        $response = $this->postJson('/api/v1/notifications', [
            'project_id' => 1,
            'recipient' => [
                'type' => 'user',
                'id' => 10,
            ],
            'source' => [
                'service' => 'cms',
                'type' => 'content_published',
                'id' => 'evt_1',
            ],
            'title' => 'Hello',
            'body' => 'World',
            'channel' => ['database'],
            'dedupe_key' => 'dup-key',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('notifications', 1);
    }
}
