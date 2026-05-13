<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;
use Exception;

class FeatureMissingException
extends SubscriptionException
{
  public function __construct(
    private readonly string $feature
  ) {

    parent::__construct(
      sprintf(
        'Required feature missing [%s].',
        $feature
      )
    );
  }

  public function context(): array
  {
    return [

      // 'code' => 'FEATURE_REQUIRED',
      'code' => SubscriptionErrorCode
      ::FEATURE_REQUIRED
        ->value,

      'feature' => $this->feature,
    ];
  }
}
