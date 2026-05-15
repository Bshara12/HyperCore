<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\NotificationPreference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NotificationPreferenceService
{
    public function listForActor(NotificationActor $actor): Collection
    {
        return NotificationPreference::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $actor->id)
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->orderBy('topic_key')
            ->orderBy('channel')
            ->get();
    }

    public function upsertForActor(NotificationActor $actor, array $preferences): Collection
    {
        return DB::transaction(function () use ($actor, $preferences) {
            foreach ($preferences as $item) {
                NotificationPreference::updateOrCreate(
                    [
                        'project_id' => $actor->projectId,
                        'recipient_type' => 'user',
                        'recipient_id' => $actor->id,
                        'topic_key' => $item['topic_key'] ?? null,
                        'channel' => $item['channel'],
                    ],
                    [
                        'enabled' => $item['enabled'],
                        'mute_until' => $item['mute_until'] ?? null,
                        'quiet_hours' => $item['quiet_hours'] ?? null,
                        'delivery_mode' => $item['delivery_mode'] ?? null,
                        'locale' => $item['locale'] ?? null,
                        'metadata' => $item['metadata'] ?? null,
                    ]
                );
            }

            return $this->listForActor($actor);
        });
    }

    public function isChannelEnabled(NotificationActor $actor, ?string $topicKey, string $channel): bool
    {
        $preference = NotificationPreference::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $actor->id)
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->where(function ($q) use ($topicKey) {
                $q->where('topic_key', $topicKey)
                    ->orWhereNull('topic_key');
            })
            ->where('channel', $channel)
            ->latest()
            ->first();

        if (! $preference) {
            return true;
        }

        if (! $preference->enabled) {
            return false;
        }

        if ($preference->mute_until && $preference->mute_until->isFuture()) {
            return false;
        }

        return true;
    }
}
