<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\ValidateBookingTimeAction;
use Carbon\Carbon;

test('it throws an exception if start time is equal to end time', function () {
  $time = Carbon::create(2026, 6, 1, 10, 0, 0);
  $action = new ValidateBookingTimeAction();

  expect(fn() => $action->execute($time, $time))
    ->toThrow(\Exception::class, 'Invalid time range');
});

test('it throws an exception if start time is after end time', function () {
  $start = Carbon::create(2026, 6, 1, 12, 0, 0);
  $end = Carbon::create(2026, 6, 1, 10, 0, 0);
  $action = new ValidateBookingTimeAction();

  expect(fn() => $action->execute($start, $end))
    ->toThrow(\Exception::class, 'Invalid time range');
});

test('it throws an exception if booking is in the past', function () {
  // تثبيت الوقت الحالي لنتمكن من اختبار "الماضي" بدقة
  Carbon::setTestNow(Carbon::create(2026, 5, 9, 12, 0, 0));

  $start = Carbon::create(2026, 5, 9, 11, 0, 0); // قبل ساعة من "الآن"
  $end = Carbon::create(2026, 5, 9, 13, 0, 0);

  $action = new ValidateBookingTimeAction();

  expect(fn() => $action->execute($start, $end))
    ->toThrow(\Exception::class, 'Cannot book past time');

  Carbon::setTestNow(); // تنظيف الوقت
});

test('it passes if time range is valid and in the future', function () {
  Carbon::setTestNow(Carbon::create(2026, 5, 9, 12, 0, 0));

  $start = Carbon::create(2026, 5, 10, 10, 0, 0); // غداً
  $end = Carbon::create(2026, 5, 10, 11, 0, 0);

  $action = new ValidateBookingTimeAction();

  // لا نتوقع أي Exception
  $action->execute($start, $end);

  expect(true)->toBeTrue();

  Carbon::setTestNow();
});
