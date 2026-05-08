<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Notifications\DTOs\NotificationActor;
use App\Models\Domains\Notifications\Models\Notification;
use App\Models\Domains\Notifications\Models\NotificationDelivery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class NotificationDeliveryTrackingService
{
    public function __construct(
        private readonly NotificationAuthorizationService $authorizationService
    ) {}

    public function listForNotification(NotificationActor $actor, string $notificationId): Collection
    {
        $notification = $this->findNotificationOrFail($actor, $notificationId);

        return $notification->deliveries()->latest()->get();
    }

    public function findDelivery(NotificationActor $actor, string $deliveryId): NotificationDelivery
    {
        $delivery = NotificationDelivery::query()
            ->with('notification')
            ->whereKey($deliveryId)
            ->first();

        if (! $delivery) {
            throw new ModelNotFoundException('Delivery not found.');
        }

        // $this->authorizeNotificationAccess($actor, $delivery->notification);

        return $delivery;
    }

    private function findNotificationOrFail(NotificationActor $actor, string $notificationId): Notification
    {
        $query = Notification::query()
            ->whereKey($notificationId)
            ->when($actor->isUser(), function ($q) use ($actor) {
                $q->where('recipient_type', 'user')
                  ->where('recipient_id', $actor->id);
            })
            ->when($actor->projectId, fn ($q) => $q->where('project_id', $actor->projectId));

        $notification = $query->first();

        if (! $notification) {
            throw new ModelNotFoundException('Notification not found.');
        }

        $this->authorizeNotificationAccess($actor, $notification);

        return $notification;
    }

    private function authorizeNotificationAccess(NotificationActor $actor, Notification $notification): void
    {
        // user: own notifications only
        if ($actor->isUser()) {
            if ((string) $notification->recipient_type !== 'user' || (string) $notification->recipient_id !== (string) $actor->id) {
                abort(403, 'Forbidden.');
            }

            return;
        }

        // service: must stay inside project scope
        if ($actor->isService() && (string) $notification->project_id !== (string) $actor->projectId) {
            abort(403, 'Forbidden.');
        }
    }
}
