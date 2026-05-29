<?php

use App\Domains\Subscription\DTOs\Subscription\CancelSubscriptionDTO;
use App\Domains\Subscription\Requests\Subscription\CancelSubscriptionRequest;
use App\Models\Subscription;

test('it initializes with all properties correctly', function () {
  $subscription = Mockery::mock(Subscription::class);

  $dto = new CancelSubscriptionDTO(
    userId: 123,
    subscription: $subscription,
    reason: 'Too expensive'
  );

  expect($dto->userId)->toBe(123)
    ->and($dto->subscription)->toBe($subscription)
    ->and($dto->reason)->toBe('Too expensive');
});

test('it creates a DTO from request and sets user from request attributes', function () {
  // 1. إعداد الـ Mocks
  $subscription = Mockery::mock(Subscription::class);
  $request = Mockery::mock(CancelSubscriptionRequest::class);

  // 2. إعداد الـ Request Attributes
  // لأن الكود يعتمد على request()->attributes->get('auth_user')
  request()->attributes->set('auth_user', ['id' => 999]);
  $request->reason = 'Changing my mind';

  // 3. استدعاء الـ Factory Method
  $dto = CancelSubscriptionDTO::fromRequest($request, $subscription);

  // 4. التحقق من القيم
  expect($dto->userId)->toBe(999)
    ->and($dto->subscription)->toBe($subscription)
    ->and($dto->reason)->toBe('Changing my mind');
});
