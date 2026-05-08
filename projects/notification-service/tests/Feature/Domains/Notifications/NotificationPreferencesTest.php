<?php

namespace Tests\Feature\Domains\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_preferences(): void
    {
        $response = $this->putJson('/api/v1/preferences', [
            'preferences' => [
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
            ],
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('notification_preferences', [
            'project_id' => 1,
            'topic_key' => 'cms.news',
            'channel' => 'broadcast',
            'enabled' => 1,
        ]);
    }
}
