<?php

namespace Tests\Unit\Domains\Booking\DTOs\Client;

use App\Domains\Booking\DTOs\Client\RescheduleBookingDTO;
use App\Domains\Booking\Requests\RescheduleBookingRequest;

test('it can be created from reschedule request with auth user', function () {
  // 1. Arrange
  // إنشاء طلب حقيقي وتمرير البيانات إليه
  $request = new RescheduleBookingRequest([
    'booking_id' => 45,
    'start_at' => '2026-06-01 14:00:00',
    'end_at' => '2026-06-01 15:00:00',
  ]);

  // محاكاة بيانات المستخدم من الـ Middleware
  $request->attributes->set('auth_user', [
    'id' => 7
  ]);

  // 2. Act
  $dto = RescheduleBookingDTO::fromRequest($request);

  // 3. Assert
  expect($dto->bookingId)->toBe(45)
    ->and($dto->userId)->toBe(7)
    ->and($dto->startAt)->toBe('2026-06-01 14:00:00')
    ->and($dto->endAt)->toBe('2026-06-01 15:00:00');
});
