<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\UpdateBookingStatusAction;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it updates booking status and clears all related cache', function () {
  // 1. التجهيز
  $refundAmount = 50.0;
  $bookingId = 123;
  $userId = 456;
  $resourceId = 789;

  // محاكاة الموديل (Mock)
  $booking = Mockery::mock(Booking::class)->makePartial();
  $booking->id = $bookingId;
  $booking->user_id = $userId;
  $booking->resource_id = $resourceId;

  // 2. محاكاة الكاش ببيانات وهمية
  $bookingKey = CacheKeys::booking($bookingId);
  $userBookingsKey = CacheKeys::userBookings($userId);
  $resourceTag = "resource_{$resourceId}_bookings";

  Cache::put($bookingKey, 'old_data');
  Cache::put($userBookingsKey, 'old_list');
  Cache::tags([$resourceTag])->put('any_key', 'old_tag_data');

  // 3. توقعات التحديث
  $booking->shouldReceive('update')
    ->once()
    ->with([
      'status' => 'cancelled',
      'refund_amount' => $refundAmount,
      'cancellation_reason' => 'Cancelled by user',
    ])
    ->andReturn(true);

  $action = new UpdateBookingStatusAction();

  // 4. التنفيذ
  $result = $action->execute($booking, $refundAmount);

  // 5. التحققات (Assertions)
  expect($result)->toBe($booking);

  // التحقق من حذف الكاش الفردي وكاش المستخدم
  expect(Cache::has($bookingKey))->toBeFalse('Individual booking cache should be cleared');
  expect(Cache::has($userBookingsKey))->toBeFalse('User bookings list cache should be cleared');

  // التحقق من تطهير الـ Tags
  expect(Cache::tags([$resourceTag])->get('any_key'))->toBeNull();
});
