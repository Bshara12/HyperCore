<?php

namespace App\Exceptions;

use App\Domains\Subscription\Enums\SubscriptionErrorCode;

class UsageLimitExceededException
extends SubscriptionException
{
  public function __construct(
    private readonly string $feature,
    private readonly int $limit
  ) {

    parent::__construct(
      sprintf(
        'Usage limit exceeded [%s].',
        $feature
      )
    );
  }

  public function context(): array
  {
    return [

      // 'code' => 'USAGE_LIMIT_EXCEEDED',
      'code' => SubscriptionErrorCode
      ::USAGE_LIMIT_EXCEEDED
        ->value,

      'feature' => $this->feature,

      'limit' => $this->limit,
    ];
  }
}
