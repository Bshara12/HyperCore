<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\SubscriptionRequiredException;

test('it returns the static exception message', function () {
  $exception = new SubscriptionRequiredException();

  expect($exception->getMessage())->toBe('Subscription required.');
});

test('it returns the correct context with code and features', function () {
  $features = ['premium', 'advanced-reporting'];
  $exception = new SubscriptionRequiredException($features);

  // التأكد من أن الـ context يعيد الكود الثابت والميزات الممررة
  expect($exception->context())->toBe([
    'code' => 'SUBSCRIPTION_REQUIRED',
    'required_features' => $features,
  ]);
});

test('it handles empty features array correctly', function () {
  $exception = new SubscriptionRequiredException();

  // التأكد من أن المصفوفة فارغة إذا لم يتم تمرير ميزات
  expect($exception->context()['required_features'])->toBeEmpty();
});
