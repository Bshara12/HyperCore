<?php

namespace Tests\Unit\Domains\Notifications;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Domains\Notifications\Services\NotificationAuthorizationService;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class NotificationWriteServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_and_deliveries(): void
    {
        Bus::fake();

        $this->mock(NotificationAuthorizationService::class, function ($mock) {
            $mock->shouldReceive('ensureCanCreate')->once();
        });

        $this->mock(NotificationPreferenceService::class, function ($mock) {
            $mock->shouldReceive('isChannelEnabled')->andReturn(true);
        });

        $service = app(NotificationWriteService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: ['notifications.create'],
            projectId: 1
        );

        $notification = $service->create($actor, [
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
        ]);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertDatabaseHas('notifications', [
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'title' => 'Hello',
            'dedupe_key' => 'cms:content_published:evt_1:user:10',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'database',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'channel' => 'broadcast',
        ]);

        Bus::assertDispatched(DispatchNotificationDeliveryJob::class, 1);
    }

    public function test_it_returns_existing_notification_when_dedupe_matches(): void
    {
        $this->mock(NotificationAuthorizationService::class, function ($mock) {
            $mock->shouldReceive('ensureCanCreate')->once();
        });

        $this->mock(NotificationPreferenceService::class, function ($mock) {
            $mock->shouldReceive('isChannelEnabled')->andReturn(true);
        });

        $existing = Notification::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'source_type' => 'content_published',
            'source_service' => 'cms',
            'source_id' => 'evt_1',
            'created_by_type' => 'user',
            'created_by_id' => '10',
            'title' => 'Existing',
            'body' => 'Existing body',
            'priority' => 0,
            'status' => 'queued',
            'dedupe_key' => 'dup-key',
        ]);

        $service = app(NotificationWriteService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: ['notifications.create'],
            projectId: 1
        );

        $notification = $service->create($actor, [
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
            'title' => 'New',
            'body' => 'New body',
            'channel' => ['database'],
            'dedupe_key' => 'dup-key',
        ]);

        $this->assertSame($existing->id, $notification->id);
        $this->assertDatabaseCount('notifications', 1);
    }
}
