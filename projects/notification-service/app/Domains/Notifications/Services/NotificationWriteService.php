<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\SourceType;
use App\Domains\Notifications\Jobs\DispatchNotificationDeliveryJob;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationWriteService
{
    public function __construct(
        private readonly NotificationAuthorizationService $authorizationService,
        private readonly NotificationPreferenceService $preferenceService
    ) {}

    public function create(NotificationActor $actor, array $payload): Notification
    {
        if ($actor->isService()) {
            $this->authorizationService->ensureCanCreateSystem($actor);
        } else {
            $this->authorizationService->ensureCanCreate($actor);
        }

        $lockKey = 'notifications:write:' . ($payload['dedupe_key'] ?? $this->makeDedupeKey($payload));

        return Cache::lock($lockKey, 15)->block(5, function () use ($actor, $payload) {
            return DB::transaction(function () use ($actor, $payload) {
                $existing = $this->findDuplicate($payload);

                if ($existing) {
                    return $existing->load(['deliveries', 'template']);
                }

                $template = $this->resolveTemplate($payload);

                $title = $payload['title'] ?? null;
                $body = $payload['body'] ?? null;

                if ($template) {
                    $rendered = $this->renderTemplate($template, $payload['data'] ?? []);
                    $title = $title ?? $rendered['title'];
                    $body = $body ?? $rendered['body'];
                }

                $isScheduled = ! empty($payload['scheduled_at']);
                $status = $isScheduled ? NotificationStatus::Pending : NotificationStatus::Queued;

                $notification = Notification::create([
                    'project_id' => $payload['project_id'],
                    'recipient_type' => $payload['recipient']['type'],
                    'recipient_id' => $payload['recipient']['id'],

                    'source_type' => $payload['source']['type'] ?? SourceType::DomainEvent->value,
                    'source_service' => $payload['source']['service'] ?? null,
                    'source_id' => $payload['source']['id'] ?? null,

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

                    'template_id' => $template?->id,
                    'topic_key' => $payload['topic_key'] ?? null,
                    'title' => $title ?? '',
                    'body' => $body,
                    'data' => $payload['data'] ?? [],
                    'metadata' => $payload['metadata'] ?? [],
                    'priority' => $payload['priority'] ?? 0,
                    'status' => $status,
                    'scheduled_at' => $payload['scheduled_at'] ?? null,
                    'dedupe_key' => $payload['dedupe_key'] ?? null,
                ]);

                $channels = $payload['channel'] ?? ['database'];

                foreach ($channels as $channel) {
                    $shouldSend = $this->preferenceService->isChannelEnabled(
                        actor: $actor,
                        topicKey: $payload['topic_key'] ?? null,
                        channel: $channel
                    );

                    if (! $shouldSend) {
                        continue;
                    }

                    $delivery = NotificationDelivery::create([
                        'notification_id' => $notification->id,
                        'channel' => $channel,
                        'status' => $channel === 'database' ? 'delivered' : 'pending',
                        'attempts' => 0,
                        'max_attempts' => 3,
                        'payload_snapshot' => [
                            'title' => $notification->title,
                            'body' => $notification->body,
                            'data' => $notification->data,
                        ],
                        'sent_at' => $channel === 'database' ? now() : null,
                        'delivered_at' => $channel === 'database' ? now() : null,
                    ]);

                    if ($channel !== 'database' && ! $isScheduled) {
                        DispatchNotificationDeliveryJob::dispatch($delivery->id);
                    }
                }

                return $notification->load(['deliveries', 'template']);
            });
        });
    }

    private function findDuplicate(array $payload): ?Notification
    {
        if (empty($payload['dedupe_key'])) {
            return null;
        }

        return Notification::query()
            ->where('project_id', $payload['project_id'])
            ->where('recipient_type', $payload['recipient']['type'])
            ->where('recipient_id', $payload['recipient']['id'])
            ->where('dedupe_key', $payload['dedupe_key'])
            ->first();
    }

    private function resolveTemplate(array $payload): ?NotificationTemplate
    {
        if (empty($payload['template_key'])) {
            return null;
        }

        return NotificationTemplate::query()
            ->where('key', $payload['template_key'])
            ->where('is_active', true)
            ->latest('version')
            ->first();
    }

    private function renderTemplate(NotificationTemplate $template, array $data): array
    {
        $defaults = $template->defaults ?? [];
        $variables = array_merge($defaults, $data);

        $subject = $template->subject_template;
        $body = $template->body_template;

        foreach ($variables as $key => $value) {
            if ($subject !== null) {
                $subject = str_replace('{{' . $key . '}}', (string) $value, $subject);
            }

            $body = str_replace('{{' . $key . '}}', (string) $value, $body);
        }

        return [
            'title' => $subject,
            'body' => $body,
        ];
    }

    private function makeDedupeKey(array $payload): string
    {
        return hash('sha256', json_encode([
            'project_id' => $payload['project_id'] ?? null,
            'recipient' => $payload['recipient'] ?? null,
            'source' => $payload['source'] ?? null,
            'template_key' => $payload['template_key'] ?? null,
            'title' => $payload['title'] ?? null,
            'body' => $payload['body'] ?? null,
            'topic_key' => $payload['topic_key'] ?? null,
        ]));
    }
}
