<?php

namespace Tests\Integration\Domains\Booking\Repositories\Eloquent;

use App\Domains\Booking\Repositories\Eloquent\EloquentBookingRepository;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Models\Booking;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

// تحديد الكلاس لرفع التغطية البرمجية
covers(EloquentBookingRepository::class);

/**
 * دالة مساعدة مع Type Hinting لتسهيل العمل في VS Code
 */
function bookingRepo(): BookingRepositoryInterface
{
  return app(BookingRepositoryInterface::class);
}

test('it can find a booking by id with its resource', function () {
  // Arrange
  $booking = Booking::factory()->create();

  // Act
  $found = bookingRepo()->findById($booking->id);

  // Assert
  expect($found->id)->toBe($booking->id)
    ->and($found->relationLoaded('resource'))->toBeTrue();
});

test('it lists bookings by resource with all filters', function () {
  // Arrange
  $resource = Resource::factory()->create();

  // حجز مؤكد في تاريخ قديم
  Booking::factory()->create([
    'resource_id' => $resource->id,
    'status' => 'confirmed',
    'start_at' => '2026-01-01 10:00:00'
  ]);

  // حجز ملغى في تاريخ جديد
  Booking::factory()->create([
    'resource_id' => $resource->id,
    'status' => 'cancelled',
    'start_at' => '2026-02-01 10:00:00'
  ]);

  // Act & Assert
  // 1. فحص فلتر الحالة
  expect(bookingRepo()->listByResource($resource->id, status: 'confirmed'))->toHaveCount(1);

  // 2. فحص فلتر التاريخ (From)
  expect(bookingRepo()->listByResource($resource->id, from: '2026-01-15'))->toHaveCount(1);

  // 3. فحص فلتر التاريخ (To)
  expect(bookingRepo()->listByResource($resource->id, to: '2026-01-15'))->toHaveCount(1);
});

test('it lists bookings by user with optional status filter', function () {
  // Arrange
  $userId = 777;
  Booking::factory()->create(['user_id' => $userId, 'status' => 'confirmed']);
  Booking::factory()->create(['user_id' => $userId, 'status' => 'pending']);

  // Act
  $all = bookingRepo()->listByUser($userId);
  $confirmedOnly = bookingRepo()->listByUser($userId, status: 'confirmed');

  // Assert
  expect($all)->toHaveCount(2);
  expect($confirmedOnly)->toHaveCount(1);
});

test('it can create a booking from raw data', function () {
  // Arrange
  $resource = Resource::factory()->create();
  $data = [
    'resource_id' => $resource->id,
    'user_id' => 1,
    'project_id' => 1,
    'start_at' => now()->toDateTimeString(),
    'end_at' => now()->addHour()->toDateTimeString(),
    'amount' => 100.00,
    'status' => 'pending'
  ];

  // Act
  $booking = bookingRepo()->create($data);

  // Assert
  expect($booking)->toBeInstanceOf(Booking::class);
  expect(Booking::where('id', $booking->id)->exists())->toBeTrue();
});

test('it detects overlapping bookings correctly', function () {
  // Arrange
  $resource = Resource::factory()->create();
  $start = '2026-05-20 14:00:00';
  $end   = '2026-05-20 15:00:00';

  $existingBooking = Booking::factory()->create([
    'resource_id' => $resource->id,
    'start_at' => $start,
    'end_at' => $end,
    'status' => 'confirmed'
  ]);

  // Act
  // حالة 1: تداخل في المنتصف
  $overlap = bookingRepo()->countConflictingBookings($resource->id, '2026-05-20 14:30:00', '2026-05-20 15:30:00');

  // حالة 2: استخدام ignoreBookingId (تعديل الحجز الحالي)
  $ignored = bookingRepo()->countConflictingBookings($resource->id, $start, $end, $existingBooking->id);

  // حالة 3: حجز ملغى لا يجب أن يسبب تداخل
  Booking::factory()->create([
    'resource_id' => $resource->id,
    'start_at' => '2026-05-20 16:00:00',
    'end_at' => '2026-05-20 17:00:00',
    'status' => 'cancelled'
  ]);
  $cancelledOverlap = bookingRepo()->countConflictingBookings($resource->id, '2026-05-20 16:15:00', '2026-05-20 16:45:00');

  // Assert
  expect($overlap)->toBe(1);
  expect($ignored)->toBe(0);
  expect($cancelledOverlap)->toBe(0);
});

test('it gets active availabilities for a specific day', function () {
  // Arrange
  $resource = Resource::factory()->create();

  DB::table('resource_availabilities')->insert([
    'resource_id'   => $resource->id,
    'day_of_week'   => 3, // الأربعاء
    'start_time'    => '09:00:00',
    'end_time'      => '17:00:00',
    'is_active'     => true,
    'slot_duration' => 60,
    'created_at'    => now(),
    'updated_at'    => now(),
  ]);

  // Act
  $availabilities = bookingRepo()->getAvailabilitiesForDay($resource->id, 3);

  // Assert
  expect($availabilities)->toHaveCount(1);
  expect($availabilities->first()->start_time)->toBe('09:00:00');
});
