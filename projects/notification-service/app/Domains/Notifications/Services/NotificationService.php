<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\Enums\CreatorType;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Domains\Notifications\Enums\SourceType;
use App\Domains\Notifications\Jobs\BroadcastNotificationJob;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use App\Models\Domains\Notifications\Models\NotificationTemplate;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function create(array $payload, CreatorType $createdByType, string|int $createdById): Notification
    {
        return DB::transaction(function () use ($payload, $createdByType, $createdById) {
            $template = $this->resolveTemplate($payload);

            $title = $payload['title'] ?? null;
            $body = $payload['body'] ?? null;

            if ($template) {
                $rendered = $this->renderTemplate($template, $payload['data'] ?? []);
                $title = $title ?? $rendered['title'];
                $body = $body ?? $rendered['body'];
            }

            $dedupeKey = $payload['dedupe_key'] ?? null;
            if (! $dedupeKey) {
                $dedupeKey = $this->makeDedupeKey($payload);
            }

            $notification = Notification::create([
                'project_id' => $payload['project_id'],
                'recipient_type' => $payload['recipient']['type'],
                'recipient_id' => $payload['recipient']['id'],
                'source_type' => $payload['source']['type'] ?? SourceType::DomainEvent->value,
                'source_service' => $payload['source']['service'] ?? null,
                'source_id' => $payload['source']['id'] ?? null,
                'created_by_type' => $createdByType->value,
                'created_by_id' => (string) $createdById,
                'template_id' => $template?->id,
                'topic_key' => $payload['topic_key'] ?? null,
                'title' => $title ?? '',
                'body' => $body,
                'data' => $payload['data'] ?? [],
                'metadata' => $payload['metadata'] ?? [],
                'priority' => $payload['priority'] ?? 0,
                'status' => NotificationStatus::Pending,
                'scheduled_at' => $payload['scheduled_at'] ?? null,
                'dedupe_key' => $dedupeKey,
            ]);

            $channels = $payload['channel'] ?? ['database'];

            foreach ($channels as $channel) {
                NotificationDelivery::create([
                    'notification_id' => $notification->id,
                    'channel' => $channel,
                    'status' => 'pending',
                    'attempts' => 0,
                    'max_attempts' => 3,
                    'payload_snapshot' => [
                        'title' => $notification->title,
                        'body' => $notification->body,
                        'data' => $notification->data,
                    ],
                ]);
            }

            if (empty($payload['scheduled_at'])) {
                $notification->update([
                    'status' => NotificationStatus::Queued,
                    'queued_at' => now(),
                ]);
            }

            if (in_array('broadcast', $channels, true) && empty($payload['scheduled_at'])) {
                BroadcastNotificationJob::dispatch($notification->id);
            }

            return $notification->load(['deliveries', 'template']);
        });
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
            $subject = $subject ? str_replace('{{'.$key.'}}', (string) $value, $subject) : null;
            $body = str_replace('{{'.$key.'}}', (string) $value, $body);
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
