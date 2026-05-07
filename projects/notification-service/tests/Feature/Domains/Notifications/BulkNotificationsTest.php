<?php

namespace Tests\Feature\Domains\Notifications;

use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_bulk_batch(): void
    {
        NotificationSubscription::create([
            'project_id' => 1,
            'subscriber_type' => 'user',
            'subscriber_id' => 10,
            'topic_key' => 'cms.news',
            'channel_mask' => ['broadcast', 'database'],
            'filters' => null,
            'active' => true,
        ]);

        $response = $this->postJson('/api/v1/internal/notifications/bulk', [
            'project_id' => 1,
            'source' => [
                'service' => 'cms',
                'type' => 'news_published',
                'id' => 'evt_bulk_1',
            ],
            'audience' => [
                'type' => 'topic',
                'topic_key' => 'cms.news',
            ],
            'template_key' => 'cms.article_published',
            'data' => [
                'name' => 'Ahmed',
            ],
            'channel' => ['database', 'broadcast'],
            'dedupe_key' => 'bulk-1',
        ], [
            'Authorization' => 'Bearer service-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertAccepted();
        $response->assertJsonPath('data.audience_type', 'topic');
    }
}
