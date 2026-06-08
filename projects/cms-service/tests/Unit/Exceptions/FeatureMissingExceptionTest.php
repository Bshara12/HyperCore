<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\FeatureMissingException;
use App\Domains\Subscription\Enums\SubscriptionErrorCode;

test('it formats the exception message correctly', function () {
  $feature = 'real-time-analytics';
  $exception = new FeatureMissingException($feature);

  // التأكد من أن الرسالة يتم تنسيقها بشكل صحيح عبر sprintf
  expect($exception->getMessage())->toBe('Required feature missing [real-time-analytics].');
});

test('it returns the correct context with error code and feature', function () {
  $feature = 'api-access';
  $exception = new FeatureMissingException($feature);

  // التأكد من أن المصفوفة تحتوي على القيم الصحيحة
  expect($exception->context())->toBe([
    'code' => SubscriptionErrorCode::FEATURE_REQUIRED->value,
    'feature' => $feature,
  ]);
});
