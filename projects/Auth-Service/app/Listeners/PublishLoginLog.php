<?php

namespace App\Listeners;

use App\Events\UserLoggedIn;
use App\Services\RabbitMQPublisher;
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