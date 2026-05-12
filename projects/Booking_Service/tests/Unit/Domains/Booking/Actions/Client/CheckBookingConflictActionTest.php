<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\CheckBookingConflictAction;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use Carbon\Carbon;
use Mockery;

test('it passes when conflicting bookings count is less than capacity', function () {
  $resourceId = 1;
  $start = Carbon::now()->addDay();
  $end = Carbon::now()->addDay()->addHour();
  $capacity = 5;
  $ignoreBookingId = null;

  $repository = Mockery::mock(BookingRepositoryInterface::class);

  // استخدمنا Mockery::any() لتجنب مشاكل مقارنة أجزاء الثانية في Carbon
  $repository->shouldReceive('countConflictingBookings')
    ->once()
    ->with($resourceId, Mockery::any(), Mockery::any(), $ignoreBookingId)
    ->andReturn(3);

  $action = new CheckBookingConflictAction($repository);

  $action->execute($resourceId, $start, $end, $capacity, $ignoreBookingId);

  expect(true)->toBeTrue();
});

test('it throws exception when conflicting bookings count reaches capacity', function () {
  $resourceId = 1;
  $start = Carbon::now()->addDay();
  $end = Carbon::now()->addDay()->addHour();
  $capacity = 2;
  $ignoreBookingId = null;

  $repository = Mockery::mock(BookingRepositoryInterface::class);

  $repository->shouldReceive('countConflictingBookings')
    ->once()
    ->with($resourceId, Mockery::any(), Mockery::any(), $ignoreBookingId)
    ->andReturn(2);

  $action = new CheckBookingConflictAction($repository);

  expect(fn() => $action->execute($resourceId, $start, $end, $capacity, $ignoreBookingId))
    ->toThrow(\Exception::class, 'Slot is fully booked');
});

test('it passes when ignoring a specific booking id during update', function () {
  $resourceId = 1;
  $start = Carbon::now()->addDay();
  $end = Carbon::now()->addDay()->addHour();
  $capacity = 1;
  $ignoreBookingId = 123;

  $repository = Mockery::mock(BookingRepositoryInterface::class);

  $repository->shouldReceive('countConflictingBookings')
    ->once()
    ->with($resourceId, Mockery::any(), Mockery::any(), $ignoreBookingId)
    ->andReturn(0);

  $action = new CheckBookingConflictAction($repository);

  $action->execute($resourceId, $start, $end, $capacity, $ignoreBookingId);

  expect(true)->toBeTrue();
});
