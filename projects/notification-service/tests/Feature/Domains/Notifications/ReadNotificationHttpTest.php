<?php

namespace Tests\Feature\Domains\Notifications;

use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadNotificationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_and_reads_notifications(): void
    {
        $notification = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'system',
            'source_service' => 'scheduler',
            'title' => 'Unread',
            'body' => 'Body',
            'priority' => 0,
            'status' => NotificationStatus::Queued,
        ]);

        $list = $this->getJson('/api/v1/notifications', [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $list->assertOk();
        $list->assertJsonPath('meta.total', 1);

        $show = $this->getJson("/api/v1/notifications/{$notification->id}", [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $show->assertOk();
        $show->assertJsonPath('data.id', $notification->id);

        $read = $this->patchJson("/api/v1/notifications/{$notification->id}/read", [], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $read->assertOk();
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
