<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Enums\NotificationStatus;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationReadService
{
    public function __construct(
        private readonly NotificationAuthorizationService $authorizationService
    ) {}

    public function paginateForActor(
        NotificationActor $actor,
        array $filters = [],
        int $perPage = 20
    ): LengthAwarePaginator {
        $this->authorizationService->ensureCanViewAny($actor, $actor->projectId);

        return $this->baseQuery($actor, $filters)
            ->latest()
            ->paginate($perPage);
    }

    public function findForActor(NotificationActor $actor, string $id): Notification
    {
        $notification = $this->baseQuery($actor)
            ->whereKey($id)
            ->first();

        if (! $notification) {
            throw new ModelNotFoundException('Notification not found.');
        }

        $this->authorizationService->ensureCanView($actor, $notification);

        return $notification;
    }

    public function markAsRead(NotificationActor $actor, string $id): Notification
    {
        return DB::transaction(function () use ($actor, $id) {
            $notification = $this->baseQuery($actor)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if (! $notification) {
                throw new ModelNotFoundException('Notification not found.');
            }

            $this->authorizationService->ensureCanMarkAsRead($actor, $notification);

            if (is_null($notification->read_at)) {
                $notification->forceFill([
                    'read_at' => now(),
                    'status' => NotificationStatus::Read,
                ])->save();
            }

            return $notification->refresh();
        });
    }

    public function markAllAsRead(NotificationActor $actor): int
    {
        $projectId = $actor->projectId;

        $this->authorizationService->ensureCanMarkAllAsRead($actor, $projectId);

        return DB::transaction(function () use ($actor, $projectId) {
            return Notification::query()
                ->where('recipient_type', 'user')
                ->where('recipient_id', $actor->id)
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->whereNull('read_at')
                ->update([
                    'read_at' => now(),
                    'status' => NotificationStatus::Read->value,
                    'updated_at' => now(),
                ]);
        });
    }

    public function unreadCount(NotificationActor $actor): int
    {
        $this->authorizationService->ensureCanViewAny($actor, $actor->projectId);

        return $this->baseQuery($actor)
            ->whereNull('read_at')
            ->count();
    }

    private function baseQuery(NotificationActor $actor, array $filters = [])
    {
        $query = Notification::query()
            ->where('recipient_type', 'user')
            ->where('recipient_id', $actor->id)
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId));

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['unread_only'])) {
            $query->whereNull('read_at');
        }

        if (! empty($filters['topic_key'])) {
            $query->where('topic_key', $filters['topic_key']);
        }

        return $query;
    }
}
