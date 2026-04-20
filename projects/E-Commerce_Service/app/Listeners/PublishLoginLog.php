<?php

namespace App\Listeners;

use App\Domains\CMS\Services\RabbitMQPublisher;
use App\Events\UserLoggedIn;

use Illuminate\Support\Str;

class PublishLoginLog
{
    public function handle(UserLoggedIn $event)
    {
        app(RabbitMQPublisher::class)->publish([
            'event_id' => (string) Str::uuid(),
            'module' => 'auth',
            'event_type' => 'login',
            'user_id' => $event->userId,
            'occurred_at' => now()->toDateTimeString(),
        ]);
    }
}