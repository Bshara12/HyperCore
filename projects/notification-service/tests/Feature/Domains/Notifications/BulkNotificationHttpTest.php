<?php

namespace Tests\Feature\Domains\Notifications;

use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkNotificationHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_bulk_batch_via_http(): void
    {
        NotificationSubscription::create([
            'project_id' => 1,
            'subscriber_type' => 'user',
            'subscriber_id' => 10,
            'topic_key' => 'cms.news',
            'channel_mask' => ['database'],
            'filters' => null,
            'active' => true,
        ]);

        $response = $this->postJson('/api/v1/internal/notifications/bulk', [
            'project_id' => 1,
            'source' => [
                'service' => 'cms',
                'type' => 'news_published',
                'id' => 'evt_100',
            ],
            'audience' => [
                'type' => 'topic',
                'topic_key' => 'cms.news',
            ],
            'template_key' => 'cms.article_published',
            'data' => [
                'name' => 'Ahmed',
            ],
            'channel' => ['database'],
            'dedupe_key' => 'bulk-100',
        ], [
            'Authorization' => 'Bearer service-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('data.audience_type', 'topic');

        $this->assertDatabaseHas('notification_batches', [
            'project_id' => 1,
            'audience_type' => 'topic',
            'dedupe_key' => 'bulk-100',
        ]);
    }
}
