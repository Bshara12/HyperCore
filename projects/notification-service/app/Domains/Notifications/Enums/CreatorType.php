<?php

namespace App\Domains\Notifications\Enums;

enum CreatorType: string
{
    case User = 'user';
    case Service = 'service';
}
