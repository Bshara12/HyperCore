<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Domains\Notifications\Policies\NotificationPolicy;
use App\Models\Domains\Notifications\Models\Notification;
use Illuminate\Auth\Access\AuthorizationException;

class NotificationAuthorizationService
{
    public function __construct(
        private readonly NotificationPolicy $policy
    ) {}

    public function ensureCanCreate(NotificationActor $actor): void
    {
        if (! $this->policy->create($actor)) {
            throw new AuthorizationException('You are not authorized to create notifications.');
        }
    }

    public function ensureCanCreateSystem(NotificationActor $actor): void
    {
        if (! $this->policy->createSystem($actor)) {
            throw new AuthorizationException('You are not authorized to create system notifications.');
        }
    }

    public function ensureCanViewAny(NotificationActor $actor, ?string $projectId = null): void
    {
        if (! $this->policy->viewAny($actor, $projectId)) {
            throw new AuthorizationException('You are not authorized to view notifications.');
        }
    }

    public function ensureCanView(NotificationActor $actor, Notification $notification): void
    {
        if (! $this->policy->view($actor, $notification)) {
            throw new AuthorizationException('You are not authorized to view this notification.');
        }
    }

    public function ensureCanMarkAsRead(NotificationActor $actor, Notification $notification): void
    {
        if (! $this->policy->markAsRead($actor, $notification)) {
            throw new AuthorizationException('You are not authorized to mark this notification as read.');
        }
    }

    public function ensureCanMarkAllAsRead(NotificationActor $actor, ?string $projectId = null): void
    {
        if (! $this->policy->markAllAsRead($actor, $projectId)) {
            throw new AuthorizationException('You are not authorized to mark notifications as read.');
        }
    }
}
