<?php

use App\Domains\Subscription\DTOs\Subscription\SubscribeUserDTO;
use App\Domains\Subscription\Requests\Subscription\SubscribeUserRequest;

test('it initializes with all properties correctly', function () {
  $dto = new SubscribeUserDTO(
    userId: 1,
    userName: 'Test User',
    planId: 10,
    gateway: 'stripe',
    paymentType: 'credit_card',
    autoRenew: true,
    metadata: ['campaign' => 'black_friday']
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->userName)->toBe('Test User')
    ->and($dto->planId)->toBe(10)
    ->and($dto->autoRenew)->toBeTrue();
});

test('it creates a DTO from request and retrieves user from auth attributes', function () {
  // 1. محاكاة بيانات المستخدم في الـ Request Attributes
  request()->attributes->set('auth_user', [
    'id' => 55,
    'name' => 'Mohamed Ali'
  ]);

  // 2. محاكاة الـ SubscribeUserRequest
  $request = Mockery::mock(SubscribeUserRequest::class);

  // تعيين القيم التي يقرأها الـ DTO
  $request->plan_id = 20;
  $request->gateway = 'paypal';
  $request->payment_type = 'wallet';
  $request->metadata = ['ref' => 'landing_page'];

  // محاكاة ميثود الـ boolean
  $request->shouldReceive('boolean')
    ->once()
    ->with('auto_renew', true)
    ->andReturn(true);

  // 3. استدعاء الـ Factory Method
  $dto = SubscribeUserDTO::fromRequest($request);

  // 4. التحقق
  expect($dto->userId)->toBe(55)
    ->and($dto->userName)->toBe('Mohamed Ali')
    ->and($dto->planId)->toBe(20)
    ->and($dto->gateway)->toBe('paypal')
    ->and($dto->autoRenew)->toBeTrue()
    ->and($dto->metadata)->toBe(['ref' => 'landing_page']);
});
