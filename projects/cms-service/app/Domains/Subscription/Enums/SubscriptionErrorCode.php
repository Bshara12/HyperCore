<?php

namespace App\Domains\Subscription\Enums;

enum SubscriptionErrorCode: string
{
    case SUBSCRIPTION_REQUIRED =
        'SUBSCRIPTION_REQUIRED';

    case FEATURE_REQUIRED =
        'FEATURE_REQUIRED';

    case FEATURE_DISABLED =
        'FEATURE_DISABLED';

    case USAGE_LIMIT_EXCEEDED =
        'USAGE_LIMIT_EXCEEDED';

    case SUBSCRIPTION_EXPIRED =
        'SUBSCRIPTION_EXPIRED';
}