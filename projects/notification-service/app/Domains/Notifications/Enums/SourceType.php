<?php

namespace App\Domains\Notifications\Enums;

enum SourceType: string
{
    case UserDriven = 'user';
    case System = 'system';
    case DomainEvent = 'domain_event';
}
