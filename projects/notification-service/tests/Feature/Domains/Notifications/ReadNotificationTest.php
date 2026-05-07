<?php

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_notifications(): void
    {
        Notification::create([
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

        $response = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_it_marks_notification_as_read(): void
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

        $response = $this->patchJson("/api/v1/notifications/{$notification->id}/read", [], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
        $this->assertSame(NotificationStatus::Read->value, $notification->fresh()->status->value);
    }

    public function test_it_returns_unread_count(): void
    {
        Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Unread 1',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $response = $this->getJson('/api/v1/notifications/unread-count', [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.count', 1);
    }

    public function test_it_marks_all_as_read(): void
    {
        Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Unread 1',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Unread 2',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $response = $this->patchJson('/api/v1/notifications/read-all', [], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated_count', 2);
    }
}
