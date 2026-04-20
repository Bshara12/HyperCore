<?php

namespace App\Listeners;

use App\Domains\CMS\Services\RabbitMQPublisher;
use App\Events\SystemLogEvent;
use Illuminate\Support\Str;

class PublishSystemLog
{
    public function handle(SystemLogEvent $event)
    {
        $data = [
            'event_id' => (string) Str::uuid(),
            'module' => $event->module,
            'event_type' => $event->eventType,
            'user_id' => $event->userId,
            'entity_type' => $event->entityType,
            'entity_id' => $event->entityId,
            'occurred_at' => now()->toDateTimeString(),
        ];

        if ($event->eventType === 'audit') {
            $data['old_values'] = $event->oldValues;
            $data['new_values'] = $event->newValues;
        }

        app(RabbitMQPublisher::class)->publish($data);
    }
}