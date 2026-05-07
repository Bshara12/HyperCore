<?php

namespace Tests\Unit\Domains\Notifications\Jobs;

use App\Domains\Notifications\DTOs\NotificationActor\NotificationBatchService;
use App\Domains\Notifications\Jobs\MaterializeNotificationBatchJob;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MaterializeNotificationBatchJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_materializes_notifications_for_resolved_recipients(): void
    {
        $batch = NotificationBatch::create([
            'project_id' => 1,
            'created_by_type' => 'service',
            'created_by_id' => 'svc_1',
            'source_service' => 'cms',
            'source_event_type' => 'news_published',
            'audience_type' => 'topic',
            'audience_query' => [
                'topic_key' => 'cms.news',
            ],
            'payload' => [
                'title' => 'News',
                'body' => 'Published',
                'channel' => ['database'],
            ],
            'status' => 'queued',
            'dedupe_key' => 'batch-1',
        ]);

        $batchService = Mockery::mock(NotificationBatchService::class);
        $batchService->shouldReceive('resolveRecipients')
            ->once()
            ->andReturn([
                ['type' => 'user', 'id' => 10],
                ['type' => 'user', 'id' => 11],
            ]);

        $writeService = Mockery::mock(NotificationWriteService::class);
        $writeService->shouldReceive('create')
            ->twice()
            ->andReturnUsing(function () {
                return Notification::make([
                    'id' => '01HXXXXXXXXXXXXXXX',
                    'title' => 'News',
                ]);
            });

        $job = new MaterializeNotificationBatchJob($batch->id);
        $job->handle($batchService, $writeService);

        $this->assertSame('completed', $batch->fresh()->status);
        $this->assertSame(2, $batch->fresh()->processed_targets);
        $this->assertSame(2, $batch->fresh()->total_targets);
    }
}
