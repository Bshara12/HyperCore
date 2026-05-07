<?php

namespace Tests\Unit\Domains\Notifications;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Services\NotificationPreferenceService;
use App\Models\Domains\Notifications\Models\NotificationPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_upserts_preferences_for_actor(): void
    {
        $service = app(NotificationPreferenceService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: [],
            projectId: 1
        );

        $preferences = $service->upsertForActor($actor, [
            [
                'topic_key' => 'cms.news',
                'channel' => 'broadcast',
                'enabled' => true,
                'delivery_mode' => 'instant',
            ],
            [
                'topic_key' => 'cms.news',
                'channel' => 'email',
                'enabled' => false,
                'delivery_mode' => 'muted',
            ],
        ]);

        $this->assertCount(2, $preferences);

        $this->assertDatabaseHas('notification_preferences', [
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'topic_key' => 'cms.news',
            'channel' => 'broadcast',
            'enabled' => 1,
        ]);

        $this->assertDatabaseHas('notification_preferences', [
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'topic_key' => 'cms.news',
            'channel' => 'email',
            'enabled' => 0,
        ]);
    }

    public function test_it_returns_false_when_channel_is_muted(): void
    {
        NotificationPreference::create([
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'topic_key' => 'cms.news',
            'channel' => 'broadcast',
            'enabled' => false,
            'mute_until' => null,
            'quiet_hours' => null,
            'delivery_mode' => 'muted',
            'locale' => null,
            'metadata' => null,
        ]);

        $service = app(NotificationPreferenceService::class);

        $actor = new NotificationActor(
            type: 'user',
            id: 10,
            permissions: [],
            projectId: 1
        );

        $enabled = $service->isChannelEnabled($actor, 'cms.news', 'broadcast');

        $this->assertFalse($enabled);
    }
}
