<?php

namespace App\Domains\Notifications\Contracts;

use App\Models\Domains\Notifications\Models\NotificationDelivery;

interface NotificationChannelDriver
{
    public function send(NotificationDelivery $delivery): void;
}
