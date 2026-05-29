<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\UsageLimitExceededException;
use App\Domains\Subscription\Enums\SubscriptionErrorCode;

test('it formats the exception message correctly', function () {
  $feature = 'api-calls';
  $limit = 1000;
  $exception = new UsageLimitExceededException($feature, $limit);

  // التأكد من أن الرسالة يتم تنسيقها بشكل صحيح
  expect($exception->getMessage())->toBe('Usage limit exceeded [api-calls].');
});

test('it returns the correct context with error code, feature, and limit', function () {
  $feature = 'storage-space';
  $limit = 500;
  $exception = new UsageLimitExceededException($feature, $limit);

  // التأكد من أن المصفوفة تحتوي على جميع البيانات المطلوبة بدقة
  expect($exception->context())->toBe([
    'code' => SubscriptionErrorCode::USAGE_LIMIT_EXCEEDED->value,
    'feature' => $feature,
    'limit' => $limit,
  ]);
});
