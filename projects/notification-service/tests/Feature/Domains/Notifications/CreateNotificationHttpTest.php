<?php

namespace Tests\Feature\Domains\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateNotificationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_notification_via_http(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => [
                'type' => 'user',
                'id' => 10,
            ],
            'source' => [
                'service' => 'cms',
                'type' => 'content_published',
                'id' => 'evt_1',
            ],
            'title' => 'Published',
            'body' => 'Your article is live',
            'channel' => ['database'],
            'dedupe_key' => 'cms:content_published:evt_1:user:10',
        ], [
            'Authorization' => 'Bearer test-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Published');

        $this->assertDatabaseHas('notifications', [
            'project_id' => 1,
            'recipient_type' => 'user',
            'recipient_id' => 10,
            'title' => 'Published',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'channel' => 'database',
        ]);
    }

    public function test_it_rejects_request_without_token(): void
    {
        $response = $this->postJson('/api/v1/notifications', [
            'recipient' => [
                'type' => 'user',
                'id' => 10,
            ],
            'source' => [
                'service' => 'cms',
                'type' => 'content_published',
            ],
            'title' => 'Published',
            'channel' => ['database'],
        ], [
            'X-Project-Id' => 1,
        ]);

        $response->assertUnauthorized();
    }
}
