<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\NotificationSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class NotificationSubscriptionService
{
    public function listForActor(NotificationActor $actor): Collection
    {
        return NotificationSubscription::query()
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->when($actor->isUser(), function ($q) use ($actor) {
                $q->where('subscriber_type', 'user')
                    ->where('subscriber_id', $actor->id);
            })
            ->when($actor->isService(), function ($q) {
                // service can inspect whole project scope
            })
            ->orderBy('topic_key')
            ->get();
    }

    public function createForActor(NotificationActor $actor, array $data): NotificationSubscription
    {
        return DB::transaction(function () use ($actor, $data) {
            return NotificationSubscription::updateOrCreate(
                [
                    'project_id' => $actor->projectId,
                    'subscriber_type' => 'user',
                    'subscriber_id' => $actor->id,
                    'topic_key' => $data['topic_key'],
                ],
                [
                    'channel_mask' => $data['channel_mask'] ?? null,
                    'filters' => $data['filters'] ?? null,
                    'active' => $data['active'] ?? true,
                ]
            );
        });
    }

    public function updateForActor(NotificationActor $actor, NotificationSubscription $subscription, array $data): NotificationSubscription
    {
        $this->assertOwnership($actor, $subscription);

        $subscription->update($data);

        return $subscription->refresh();
    }

    public function deleteForActor(NotificationActor $actor, NotificationSubscription $subscription): bool
    {
        $this->assertOwnership($actor, $subscription);

        return (bool) $subscription->delete();
    }

    public function syncForProject(NotificationActor $actor, array $subscriptions): Collection
    {
        return DB::transaction(function () use ($actor, $subscriptions) {
            foreach ($subscriptions as $item) {
                NotificationSubscription::updateOrCreate(
                    [
                        'project_id' => $actor->projectId,
                        'subscriber_type' => $item['subscriber_type'],
                        'subscriber_id' => $item['subscriber_id'],
                        'topic_key' => $item['topic_key'],
                    ],
                    [
                        'channel_mask' => $item['channel_mask'] ?? null,
                        'filters' => $item['filters'] ?? null,
                        'active' => $item['active'] ?? true,
                    ]
                );
            }

            return NotificationSubscription::query()
                ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
                ->orderBy('topic_key')
                ->get();
        });
    }

    public function findForActor(NotificationActor $actor, string $id): NotificationSubscription
    {
        $subscription = NotificationSubscription::query()
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId))
            ->findOrFail($id);

        $this->assertOwnership($actor, $subscription);

        return $subscription;
    }

    private function assertOwnership(NotificationActor $actor, NotificationSubscription $subscription): void
    {
        if ($actor->isUser()) {
            if (
                (string) $subscription->subscriber_type !== 'user' ||
                (string) $subscription->subscriber_id !== (string) $actor->id
            ) {
                abort(403, 'Forbidden.');
            }
        }

        if ($actor->isService()) {
            if ((string) $subscription->project_id !== (string) $actor->projectId) {
                abort(403, 'Forbidden.');
            }
        }
    }
}
