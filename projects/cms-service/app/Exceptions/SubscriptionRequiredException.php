<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;
use Exception;

class SubscriptionRequiredException
extends SubscriptionException
{
  public function __construct(
    private readonly ?string $requiredPlan = null
  ) {

    parent::__construct(
      'Subscription required.'
    );
  }

  public function context(): array
  {
    return [

      // 'code' => 'SUBSCRIPTION_REQUIRED',
      'code' => SubscriptionErrorCode
      ::SUBSCRIPTION_REQUIRED
        ->value,

      'required_plan' => $this->requiredPlan,
    ];
  }
}
