<?php

namespace Tests\Unit\Domains\Notifications;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Services\NotificationTemplateService;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTemplateServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_updates_template(): void
    {
        $service = app(NotificationTemplateService::class);

        $template = $service->create([
            'project_id' => 1,
            'key' => 'cms.article_published',
            'channel' => 'broadcast',
            'locale' => 'en',
            'version' => 1,
            'subject_template' => 'Article published',
            'body_template' => 'Hello {{name}}',
            'variables_schema' => ['name' => 'string'],
            'defaults' => ['name' => 'User'],
            'is_active' => true,
        ]);

        $this->assertInstanceOf(NotificationTemplate::class, $template);
        $this->assertDatabaseHas('notification_templates', [
            'key' => 'cms.article_published',
            'version' => 1,
        ]);

        $updated = $service->update($template, [
            'body_template' => 'Hello {{name}}, updated',
        ]);

        $this->assertSame('Hello {{name}}, updated', $updated->body_template);
    }

    public function test_it_activates_and_deactivates_template(): void
    {
        $service = app(NotificationTemplateService::class);

        $template = NotificationTemplate::create([
            'project_id' => 1,
            'key' => 'cms.article_published',
            'channel' => 'broadcast',
            'locale' => 'en',
            'version' => 1,
            'subject_template' => 'Article published',
            'body_template' => 'Hello {{name}}',
            'variables_schema' => null,
            'defaults' => null,
            'is_active' => true,
        ]);

        $service->deactivate($template);
        $this->assertFalse($template->fresh()->is_active);

        $service->activate($template);
        $this->assertTrue($template->fresh()->is_active);
    }

    public function test_it_lists_templates_for_actor(): void
    {
        NotificationTemplate::create([
            'project_id' => 1,
            'key' => 'cms.a',
            'channel' => 'broadcast',
            'locale' => 'en',
            'version' => 1,
            'subject_template' => 'A',
            'body_template' => 'Body',
            'variables_schema' => null,
            'defaults' => null,
            'is_active' => true,
        ]);

        $service = app(NotificationTemplateService::class);

        $actor = new NotificationActor(
            type: 'service',
            id: 'svc_1',
            permissions: ['notifications.manage'],
            projectId: 1
        );

        $templates = $service->listForActor($actor);

        $this->assertCount(1, $templates);
    }
}
