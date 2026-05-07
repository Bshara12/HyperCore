<?php

namespace Tests\Feature\Domains\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_template(): void
    {
        $response = $this->postJson('/api/v1/internal/templates', [
            'project_id' => 1,
            'key' => 'cms.article_published',
            'channel' => 'broadcast',
            'locale' => 'en',
            'version' => 1,
            'subject_template' => 'Article published',
            'body_template' => 'Hello {{name}}, your article is live.',
            'defaults' => [
                'name' => 'User',
            ],
            'is_active' => true,
        ], [
            'Authorization' => 'Bearer service-token',
            'X-Project-Id' => 1,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.key', 'cms.article_published');
    }
}
