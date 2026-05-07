<?php

namespace Tests\Feature\Domains\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_and_reads_preferences(): void
    {
        $update = $this->putJson('/api/v1/preferences', [
            'preferences' => [
                [
                    'topic_key' => 'cms.news',
                    'channel' => 'broadcast',
                    'enabled' => true,
                    'delivery_mode' => 'instant',
                ],
            ],
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $update->assertOk();
        $update->assertJsonCount(1, 'data');

        $read = $this->getJson('/api/v1/preferences', [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $read->assertOk();
        $read->assertJsonCount(1, 'data');
    }
}
