<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class SubscriptionFeatureDisabledException
extends SubscriptionException
{
  public function __construct(
    private readonly string $feature
  ) {

    parent::__construct(
      sprintf(
        'Feature disabled [%s].',
        $feature
      )
    );
  }

  public function context(): array
  {
    return [

      // 'code' => 'FEATURE_DISABLED',
      'code' => SubscriptionErrorCode
      ::FEATURE_DISABLED
        ->value,

      'feature' => $this->feature,
    ];
  }
}
