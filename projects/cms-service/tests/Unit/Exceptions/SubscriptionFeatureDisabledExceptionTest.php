<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\SubscriptionFeatureDisabledException;
use App\Domains\Subscription\Enums\SubscriptionErrorCode;

test('it formats the exception message correctly', function () {
  $feature = 'bulk-upload';
  $exception = new SubscriptionFeatureDisabledException($feature);

  // التأكد من أن الرسالة يتم تنسيقها بشكل صحيح عبر sprintf
  expect($exception->getMessage())->toBe('Feature disabled [bulk-upload].');
});

test('it returns the correct context with error code and feature', function () {
  $feature = 'api-integration';
  $exception = new SubscriptionFeatureDisabledException($feature);

  // التأكد من أن المصفوفة تحتوي على القيم الصحيحة
  expect($exception->context())->toBe([
    'code' => SubscriptionErrorCode::FEATURE_DISABLED->value,
    'feature' => $feature,
  ]);
});
