<?php

use App\Domains\CMS\DTOs\Rate\RateDTO;
use Illuminate\Http\Request;

test('it creates DTO from request when user is authenticated', function () {
  $request = new Request();

  // محاكاة المستخدم في الـ attributes
  $request->attributes->set('auth_user', ['id' => 42]);

  // دمج بقية بيانات الطلب
  $request->merge([
    'rateable_type' => 'App\Models\Post',
    'rateable_id' => 10,
    'rating' => 5,
    'review' => 'Great content!'
  ]);

  $dto = RateDTO::fromRequest($request);

  expect($dto->userId)->toBe(42)
    ->and($dto->rateableType)->toBe('App\Models\Post')
    ->and($dto->rateableId)->toBe(10)
    ->and($dto->rating)->toBe(5)
    ->and($dto->review)->toBe('Great content!');
});

test('it throws exception when user is unauthenticated', function () {
  $request = new Request();
  // عدم وضع auth_user في الـ attributes

  expect(fn() => RateDTO::fromRequest($request))
    ->toThrow(\Exception::class, 'Unauthenticated');
});
