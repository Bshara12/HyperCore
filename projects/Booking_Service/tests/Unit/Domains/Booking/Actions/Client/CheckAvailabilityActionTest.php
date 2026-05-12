<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\CheckAvailabilityAction;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use Carbon\Carbon;
use Mockery;

test('it passes when the booking interval is perfectly inside availability', function () {
  // 1. التجهيز: اختيار يوم اثنين (Carbon dayOfWeek = 1)
  $resourceId = 1;
  $start = Carbon::create(2026, 5, 11, 10, 0, 0); // 10:00 AM
  $end = Carbon::create(2026, 5, 11, 12, 0, 0);   // 12:00 PM

  // محاكاة فترات توفر تغطي الوقت المطلوب
  $availabilities = collect([
    (object)['start_time' => '08:00:00', 'end_time' => '16:00:00']
  ]);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('getAvailabilitiesForDay')
    ->once()
    ->with($resourceId, 1)
    ->andReturn($availabilities);

  $action = new CheckAvailabilityAction($repository);

  // التنفيذ: لا نتوقع رمي Exception
  $action->execute($resourceId, $start, $end);

  expect(true)->toBeTrue();
});

test('it throws exception if the time is outside the available ranges', function () {
  $resourceId = 1;
  // حجز يبدأ في وقت متأخر (الساعة 5 مساءً)
  $start = Carbon::create(2026, 5, 11, 17, 0, 0);
  $end = Carbon::create(2026, 5, 11, 18, 0, 0);

  // توفر ينتهي في الساعة 4 مساءً
  $availabilities = collect([
    (object)['start_time' => '08:00:00', 'end_time' => '16:00:00']
  ]);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('getAvailabilitiesForDay')->andReturn($availabilities);

  $action = new CheckAvailabilityAction($repository);

  // التحقق من رمي الاستثناء الصحيح
  expect(fn() => $action->execute($resourceId, $start, $end))
    ->toThrow(\Exception::class, 'Time out of times availability');
});

test('it throws exception when the resource has no availability defined for that day', function () {
  $resourceId = 1;
  $start = Carbon::create(2026, 5, 11, 10, 0, 0);
  $end = Carbon::create(2026, 5, 11, 11, 0, 0);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  // إرجاع مجموعة فارغة
  $repository->shouldReceive('getAvailabilitiesForDay')
    ->andReturn(collect([]));

  $action = new CheckAvailabilityAction($repository);

  expect(fn() => $action->execute($resourceId, $start, $end))
    ->toThrow(\Exception::class, 'No availability for this day');
});

test('it supports multiple availability slots for the same day', function () {
  $resourceId = 1;
  // حجز في الفترة المسائية
  $start = Carbon::create(2026, 5, 11, 14, 0, 0);
  $end = Carbon::create(2026, 5, 11, 15, 0, 0);

  // فترتان: صباحية ومسائية
  $availabilities = collect([
    (object)['start_time' => '08:00:00', 'end_time' => '12:00:00'],
    (object)['start_time' => '13:00:00', 'end_time' => '17:00:00']
  ]);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('getAvailabilitiesForDay')->andReturn($availabilities);

  $action = new CheckAvailabilityAction($repository);

  // يجب أن يمر بنجاح لأنه يقع في الفترة الثانية
  $action->execute($resourceId, $start, $end);
  expect(true)->toBeTrue();
});
