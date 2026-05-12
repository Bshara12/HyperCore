<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\GetBookingAction;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it returns booking from repository and stores it in cache', function () {
  $bookingId = 1;
  $cacheKey = CacheKeys::booking($bookingId);

  // استخدام forceFill لضمان تعيين الـ ID
  $mockBooking = (new Booking())->forceFill(['id' => $bookingId]);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('findById')
    ->once()
    ->with($bookingId)
    ->andReturn($mockBooking);

  $action = new GetBookingAction($repository);

  $result = $action->execute($bookingId);

  // التحقق من القيمة والنوع
  expect($result)->not->toBeNull();
  expect($result->id)->toEqual($bookingId);
  expect(Cache::has($cacheKey))->toBeTrue('Booking should be stored in cache');
});

test('it returns booking directly from cache without calling repository', function () {
  $bookingId = 2;
  $cacheKey = CacheKeys::booking($bookingId);

  // استخدام forceFill للبيانات المخزنة في الكاش
  $cachedBooking = (new Booking())->forceFill(['id' => $bookingId]);

  Cache::put($cacheKey, $cachedBooking, CacheKeys::TTL_SHORT);

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldNotReceive('findById');

  $action = new GetBookingAction($repository);

  $result = $action->execute($bookingId);

  expect($result)->not->toBeNull();
  expect($result->id)->toEqual($bookingId);
});

test('it throws an exception when booking is not found', function () {
  $bookingId = 999;

  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('findById')
    ->once()
    ->with($bookingId)
    ->andReturn(null);

  $action = new GetBookingAction($repository);

  expect(fn() => $action->execute($bookingId))
    ->toThrow(\Exception::class, 'Booking not found');
});
