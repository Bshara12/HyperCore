<?php

namespace Tests\Unit\Domains\Booking\DTOs\Client;

use App\Domains\Booking\DTOs\Client\CancelBookingDTO;
use App\Domains\Booking\Requests\CancelBookingRequest;
use Symfony\Component\HttpFoundation\ParameterBag;

test('it can be created from request with authenticated user', function () {
    // 1. Arrange
    // نستخدم makePartial للسماح للـ Request بالعمل بشكل طبيعي مع الـ attributes
  /** @var CancelBookingRequest|\Mockery\MockInterface $request */
  $request = \Mockery::mock(CancelBookingRequest::class)->makePartial();

  // محاكاة سمة المستخدم (auth_user) كما يفعل الـ Middleware الخاص بك
  $request->attributes = new ParameterBag([
    'auth_user' => ['id' => 99]
  ]);

  // محاكاة معرف الحجز
  $request->shouldReceive('all')->andReturn(['booking_id' => 500]);
  $request->shouldReceive('__get')->with('booking_id')->andReturn(500);

  // 2. Act
  $dto = CancelBookingDTO::fromRequest($request);

  // 3. Assert
  expect($dto->bookingId)->toBe(500)
    ->and($dto->userId)->toBe(99);
});

test('it throws an exception if user is not authenticated', function () {
    // 1. Arrange
  /** @var CancelBookingRequest|\Mockery\MockInterface $request */
  $request = \Mockery::mock(CancelBookingRequest::class)->makePartial();

  // محاكاة عدم وجود مستخدم
  $request->attributes = new ParameterBag([]);

  // 2. Act & Assert
  // نتوقع أن يرمي الدالة استثناء عند استدعائها
  expect(fn() => CancelBookingDTO::fromRequest($request))
    ->toThrow(\Exception::class, 'Unauthenticated');
});
