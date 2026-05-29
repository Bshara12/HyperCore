<?php

use App\Domains\Subscription\DTOs\Subscription\RenewSubscriptionDTO;
use App\Domains\Subscription\Requests\Subscription\RenewSubscriptionRequest;
use App\Models\Subscription;

test('it initializes with all properties correctly', function () {
  $subscription = Mockery::mock(Subscription::class);

  $dto = new RenewSubscriptionDTO(
    userId: 1,
    userName: 'Ahmed Ali',
    subscription: $subscription,
    gateway: 'stripe',
    paymentType: 'credit_card',
    autoRenew: true,
    metadata: ['promo' => 'SUMMER2026']
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->userName)->toBe('Ahmed Ali')
    ->and($dto->subscription)->toBe($subscription)
    ->and($dto->gateway)->toBe('stripe')
    ->and($dto->paymentType)->toBe('credit_card')
    ->and($dto->autoRenew)->toBeTrue()
    ->and($dto->metadata)->toBe(['promo' => 'SUMMER2026']);
});

test('it creates a DTO from request and sets user data from auth_user attribute', function () {
  // 1. تجهيز الـ Mocks
  $subscription = Mockery::mock(Subscription::class);
  $request = Mockery::mock(RenewSubscriptionRequest::class);

  // 2. محاكاة الـ Auth User في الـ Request Attributes
  request()->attributes->set('auth_user', [
    'id' => 999,
    'name' => 'John Doe'
  ]);

  // 3. تجهيز بيانات الـ Request
  $request->gateway = 'paypal';
  $request->payment_type = 'direct_debit';
  $request->auto_renew = false;
  $request->metadata = null;

  // 4. استدعاء الـ Factory Method
  $dto = RenewSubscriptionDTO::fromRequest($request, $subscription);

  // 5. التحقق
  expect($dto->userId)->toBe(999)
    ->and($dto->userName)->toBe('John Doe')
    ->and($dto->gateway)->toBe('paypal')
    ->and($dto->paymentType)->toBe('direct_debit')
    ->and($dto->autoRenew)->toBeFalse()
    ->and($dto->subscription)->toBe($subscription);
});
