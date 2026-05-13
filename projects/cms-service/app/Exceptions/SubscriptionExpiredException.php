<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class SubscriptionExpiredException
extends SubscriptionException
{
  public function context(): array
  {
    return [

      // 'code' => 'SUBSCRIPTION_EXPIRED',
      'code' => SubscriptionErrorCode
      ::SUBSCRIPTION_EXPIRED
        ->value,
    ];
  }
}
