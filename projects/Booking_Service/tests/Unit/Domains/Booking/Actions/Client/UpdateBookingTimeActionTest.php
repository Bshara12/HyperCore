<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\UpdateBookingTimeAction;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it updates booking time and clears relevant cache', function () {
  // 1. التجهيز
  $bookingId = 101;
  $resourceId = 202;
  $newStart = '2026-06-01 10:00:00';
  $newEnd = '2026-06-01 11:00:00';

  // محاكاة الموديل
  $booking = Mockery::mock(Booking::class)->makePartial();
  $booking->id = $bookingId;
  $booking->resource_id = $resourceId;

  // 2. محاكاة الكاش
  $bookingKey = CacheKeys::booking($bookingId);
  $resourceTag = "resource_{$resourceId}_bookings";

  Cache::put($bookingKey, 'old_time_data');
  Cache::tags([$resourceTag])->put('calendar_grid', 'old_layout');

  // 3. توقعات التحديث
  $booking->shouldReceive('update')
    ->once()
    ->with([
      'start_at' => $newStart,
      'end_at' => $newEnd,
    ])
    ->andReturn(true);

  $action = new UpdateBookingTimeAction();

  // 4. التنفيذ
  $result = $action->execute($booking, $newStart, $newEnd);

  // 5. التحققات
  expect($result)->toBe($booking);

  // التحقق من حذف الكاش الفردي
  expect(Cache::has($bookingKey))->toBeFalse('Individual booking cache must be cleared');

  // التحقق من تطهير الـ Tags (يجب أن يعود null بعد الـ flush)
  expect(Cache::tags([$resourceTag])->get('calendar_grid'))->toBeNull();
});
