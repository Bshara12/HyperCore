<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Jobs\MaterializeNotificationBatchJob;
use App\Models\Domains\Notifications\Models\NotificationBatch;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationBatchService
{
    public function __construct(
        private readonly NotificationPreferenceService $preferenceService
    ) {}

    public function create(NotificationActor $actor, array $payload): NotificationBatch
    {
        $lockKey = 'notifications:batch:' . ($payload['dedupe_key'] ?? hash('sha256', json_encode($payload)));

        return Cache::lock($lockKey, 30)->block(5, function () use ($actor, $payload) {
            return DB::transaction(function () use ($actor, $payload) {
                $batch = NotificationBatch::create([
                    'project_id' => $payload['project_id'],
                    'created_by_type' => $actor->isService() ? CreatorType::Service->value : CreatorType::User->value,
                    'created_by_id' => (string) $actor->id,
                    'correlation_id' => $actor->correlationId,
                    'causation_id' => $actor->causationId,
                    'request_id' => $actor->requestId,
                    'actor_snapshot' => $actor->snapshot(),
                    'source_snapshot' => $payload['source'] ?? null,
                    'audit_meta' => [
                        'ip' => $actor->ip,
                        'user_agent' => $actor->userAgent,
                    ],
                    'source_service' => $payload['source']['service'],
                    'source_event_type' => $payload['source']['type'],
                    'audience_type' => $payload['audience']['type'],
                    'audience_query' => $payload['audience'],
                    'payload' => [
                        'template_key' => $payload['template_key'] ?? null,
                        'title' => $payload['title'] ?? null,
                        'body' => $payload['body'] ?? null,
                        'data' => $payload['data'] ?? [],
                        'metadata' => $payload['metadata'] ?? [],
                        'channel' => $payload['channel'] ?? ['database'],
                        'scheduled_at' => $payload['scheduled_at'] ?? null,
                        'dedupe_key' => $payload['dedupe_key'] ?? null,
                        'source' => $payload['source'],
                    ],
                    'status' => 'queued',
                    'dedupe_key' => $payload['dedupe_key'] ?? null,
                    'scheduled_at' => $payload['scheduled_at'] ?? null,
                ]);

                MaterializeNotificationBatchJob::dispatch($batch->id);

                return $batch;
            });
        });
    }

    public function resolveRecipients(NotificationBatch $batch): array
    {
        if ($batch->audience_type === 'custom') {
            return data_get($batch->audience_query, 'recipients', []);
        }

        if ($batch->audience_type === 'topic') {
            $topicKey = data_get($batch->audience_query, 'topic_key');

            if (! $topicKey) {
                return [];
            }

            return NotificationSubscription::query()
                ->where('project_id', $batch->project_id)
                ->where('topic_key', $topicKey)
                ->where('active', true)
                ->get()
                ->map(fn ($subscription) => [
                    'type' => $subscription->subscriber_type,
                    'id' => $subscription->subscriber_id,
                ])
                ->unique(fn ($item) => $item['type'] . ':' . $item['id'])
                ->values()
                ->all();
        }

        return [];
    }
}
