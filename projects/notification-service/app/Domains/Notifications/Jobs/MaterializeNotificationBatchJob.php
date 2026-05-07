<?php

namespace App\Domains\Notifications\Jobs;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use App\Domains\Notifications\Services\NotificationBatchService;
use App\Domains\Notifications\Services\NotificationWriteService;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class MaterializeNotificationBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use HasNotificationJobMiddleware;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(public string $batchId)
    {
    }

    protected function overlapKey(): string
    {
        return 'batch:' . $this->batchId;
    }

    protected function overlapReleaseAfter(): int
    {
        return 30;
    }

    protected function overlapExpireAfter(): int
    {
        return 600;
    }

    protected function throttleMaxExceptions(): int
    {
        return 3;
    }

    protected function throttleDecayMinutes(): int
    {
        return 10;
    }

    public function handle(
        NotificationBatchService $batchService,
        NotificationWriteService $writeService
    ): void {
        $batch = NotificationBatch::query()->findOrFail($this->batchId);

        $actor = NotificationActor::fromArray([
            'type' => $batch->created_by_type ?? 'service',
            'id' => $batch->created_by_id ?? 'system',
            'project_id' => $batch->project_id,
            'permissions' => ['notifications.manage'],
            'request_id' => $batch->request_id,
            'correlation_id' => $batch->correlation_id,
            'causation_id' => $batch->causation_id,
            'raw' => $batch->actor_snapshot ?? [],
        ]);

        $recipients = $batchService->resolveRecipients($batch);

        $batch->forceFill([
            'started_at' => now(),
            'total_targets' => count($recipients),
            'processed_targets' => 0,
            'status' => 'processing',
        ])->save();

        try {
            foreach ($recipients as $recipient) {
                $payload = array_merge($batch->payload ?? [], [
                    'project_id' => $batch->project_id,
                    'recipient' => $recipient,
                    'source' => [
                        'service' => $batch->source_service,
                        'type' => $batch->source_event_type,
                        'id' => $batch->id,
                    ],
                    'dedupe_key' => hash(
                        'sha256',
                        $batch->id . ':' . $recipient['type'] . ':' . $recipient['id']
                    ),
                ]);

                $writeService->create($actor, $payload);

                $batch->increment('processed_targets');
            }

            $batch->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $batch->forceFill([
                'status' => 'failed',
                'completed_at' => now(),
            ])->save();

            throw $e;
        }
    }
}
