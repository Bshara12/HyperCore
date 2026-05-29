<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ContentFeatureAccessDeniedException;
use App\Domains\Subscription\Enums\SubscriptionErrorCode;

test('it sets the correct exception message with formatted features', function () {
  $requiredFeatures = ['premium', 'pro', 'enterprise'];

  // إنشاء الاستثناء
  $exception = new ContentFeatureAccessDeniedException($requiredFeatures);

  // التأكد من أن الرسالة تم تنسيقها بشكل صحيح باستخدام implode
  expect($exception->getMessage())
    ->toBe('You do not have access to this content. Required one of: premium, pro, enterprise');
});

test('it returns the correct context array including the error code', function () {
  $requiredFeatures = ['premium', 'pro'];
  $exception = new ContentFeatureAccessDeniedException($requiredFeatures);

  // التأكد من أن مصفوفة السياق تحتوي على الكود الصحيح والـ features المطلوبة
  expect($exception->context())->toBe([
    'code' => SubscriptionErrorCode::FEATURE_REQUIRED->value,
    'required_features' => $requiredFeatures,
  ]);
});
