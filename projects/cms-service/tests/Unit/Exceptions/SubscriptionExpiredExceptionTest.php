<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\SubscriptionExpiredException;
use App\Domains\Subscription\Enums\SubscriptionErrorCode;

test('it returns the correct context with subscription expired error code', function () {
  $exception = new SubscriptionExpiredException();

  // التأكد من أن دالة السياق تعيد الكود الصحيح
  expect($exception->context())->toBe([
    'code' => SubscriptionErrorCode::SUBSCRIPTION_EXPIRED->value,
  ]);
});
