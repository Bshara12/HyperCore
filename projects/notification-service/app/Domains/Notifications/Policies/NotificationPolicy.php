<?php

namespace App\Domains\Notifications\Policies;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\Notification;

class NotificationPolicy
{
    public function create(NotificationActor $actor): bool
    {
        if ($actor->isService()) {
            return $actor->hasPermission('notifications.create')
                || $actor->hasPermission('notifications.manage');
        }

        return $actor->isUser()
            && ($actor->hasPermission('notifications.create')
                || $actor->hasPermission('notifications.manage'));
    }

    public function createSystem(NotificationActor $actor): bool
    {
        return $actor->isService();
            // && ($actor->hasPermission('notifications.system.create')
                // || $actor->hasPermission('notifications.manage'));
    }

    public function viewAny(NotificationActor $actor, ?string $projectId = null): bool
    {
        if ($actor->isService()) {
            return $actor->hasPermission('notifications.read.any')
                || $actor->hasPermission('notifications.manage');
        }

        return $actor->isUser() && $actor->projectId === $projectId;
    }

    public function view(NotificationActor $actor, Notification $notification): bool
    {
        if ($actor->isService()) {
            return $actor->hasPermission('notifications.read.any')
                || $actor->hasPermission('notifications.manage');
        }

        return $actor->isUser()
            && (string) $notification->recipient_type === 'user'
            && (string) $notification->recipient_id === (string) $actor->id;
            // && (string) $notification->project_id === (string) $actor->projectId;
    }

    public function markAsRead(NotificationActor $actor, Notification $notification): bool
    {
        return $this->view($actor, $notification);
    }

    public function markAllAsRead(NotificationActor $actor, ?string $projectId = null): bool
    {
        if ($actor->isService()) {
            return $actor->hasPermission('notifications.manage');
        }

        return $actor->isUser() && $actor->projectId === $projectId;
    }
}
