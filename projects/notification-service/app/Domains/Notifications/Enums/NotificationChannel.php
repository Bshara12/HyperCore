<?php

namespace App\Domains\Notifications\Enums;

enum NotificationChannel: string
{
    case Database = 'database';
    case Broadcast = 'broadcast';
    case Email = 'email';
    case Webhook = 'webhook';
}
