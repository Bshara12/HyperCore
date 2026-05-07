<?php

namespace Tests\Unit\Domains\Notifications;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Services\NotificationAuthorizationService;
use App\Domains\Notifications\Services\NotificationReadService;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationReadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_unread_notifications(): void
    {
        $this->mock(NotificationAuthorizationService::class, function ($mock) {
            $mock->shouldReceive('ensureCanViewAny')->once();
        });

        Notification::create([
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

        $service = app(NotificationReadService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: ['notifications.read.any'],
            projectId: 1
        );

        $count = $service->unreadCount($actor);

        $this->assertSame(1, $count);
    }

    public function test_it_marks_all_as_read(): void
    {
        $this->mock(NotificationAuthorizationService::class, function ($mock) {
            $mock->shouldReceive('ensureCanMarkAllAsRead')->once();
        });

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

        $service = app(NotificationReadService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: ['notifications.manage'],
            projectId: 1
        );

        $updated = $service->markAllAsRead($actor);

        $this->assertSame(2, $updated);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseHas('notifications', [
            'recipient_id' => 10,
        ]);
    }

    public function test_it_marks_notification_as_read(): void
    {
        $this->mock(NotificationAuthorizationService::class, function ($mock) {
            $mock->shouldReceive('ensureCanMarkAsRead')->once();
        });

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

        $service = app(NotificationReadService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: ['notifications.manage'],
            projectId: 1
        );

        $updated = $service->markAsRead($actor, $notification->id);

        $this->assertNotNull($updated->read_at);
        $this->assertSame(NotificationStatus::Read->value, $updated->status);
    }
}
