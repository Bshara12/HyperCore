<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\CalculateRefundAction;
use App\Domains\Booking\Repositories\Interface\BookingCancellationPolicyRepositoryInterface;
use Carbon\Carbon;
use Mockery;

/**
 * دالة مساعدة لإنشاء حجز وهمي
 */
function createFakeBooking(int $resourceId, string $startAt, float $amount)
{
  return (object) [
    'resource_id' => $resourceId,
    'start_at'    => $startAt,
    'amount'      => $amount
  ];
}

test('it returns 0 refund if the booking has already started or is starting now', function () {
  // تثبيت الوقت الحالي
  $now = Carbon::create(2026, 5, 1, 12, 0, 0);
  Carbon::setTestNow($now);

  // حجز بدأ قبل ساعة (ساعات سلبية)
  $booking = createFakeBooking(1, $now->copy()->subHour()->toDateTimeString(), 1000);

  $repo = Mockery::mock(BookingCancellationPolicyRepositoryInterface::class);
  // لا نتوقع استدعاء الـ Repo لأن الكود يخرج مبكراً
  $repo->shouldNotReceive('getPoliciesForResource');

  $action = new CalculateRefundAction($repo);

  expect($action->execute($booking))->toBe(0.0);

  Carbon::setTestNow(); // تنظيف الوقت
});

test('it calculates refund correctly when hours match a policy', function () {
  $now = Carbon::create(2026, 5, 1, 12, 0, 0);
  Carbon::setTestNow($now);

  // حجز سيبدأ بعد 25 ساعة
  $booking = createFakeBooking(1, $now->copy()->addHours(25)->toDateTimeString(), 1000);

  // سياسات: إذا بقي 24 ساعة -> استرجع 100%، إذا بقي 12 ساعة -> استرجع 50%
  $policies = collect([
    (object) ['hours_before' => 24, 'refund_percentage' => 100],
    (object) ['hours_before' => 12, 'refund_percentage' => 50],
  ]);

  $repo = Mockery::mock(BookingCancellationPolicyRepositoryInterface::class);
  $repo->shouldReceive('getPoliciesForResource')
    ->once()
    ->with(1)
    ->andReturn($policies);

  $action = new CalculateRefundAction($repo);

  // بما أن 25 >= 24، يجب أن يرجع 1000 (100%)
  expect($action->execute($booking))->toEqual(1000.0);

  Carbon::setTestNow();
});

test('it returns partial refund for second tier policy', function () {
  $now = Carbon::create(2026, 5, 1, 12, 0, 0);
  Carbon::setTestNow($now);

  // حجز سيبدأ بعد 15 ساعة (أكبر من 12 وأقل من 24)
  $booking = createFakeBooking(1, $now->copy()->addHours(15)->toDateTimeString(), 1000);

  $policies = collect([
    (object) ['hours_before' => 24, 'refund_percentage' => 100],
    (object) ['hours_before' => 12, 'refund_percentage' => 50],
  ]);

  $repo = Mockery::mock(BookingCancellationPolicyRepositoryInterface::class);
  $repo->shouldReceive('getPoliciesForResource')->andReturn($policies);

  $action = new CalculateRefundAction($repo);

  // بما أن 15 >= 12، يجب أن يرجع 500 (50%)
  expect($action->execute($booking))->toEqual(500.0);

  Carbon::setTestNow();
});

test('it returns 0 if hours are less than the lowest policy threshold', function () {
  $now = Carbon::create(2026, 5, 1, 12, 0, 0);
  Carbon::setTestNow($now);

  // حجز سيبدأ بعد 5 ساعات فقط
  $booking = createFakeBooking(1, $now->copy()->addHours(5)->toDateTimeString(), 1000);

  // أقل سياسة تتطلب 12 ساعة
  $policies = collect([
    (object) ['hours_before' => 12, 'refund_percentage' => 50],
  ]);

  $repo = Mockery::mock(BookingCancellationPolicyRepositoryInterface::class);
  $repo->shouldReceive('getPoliciesForResource')->andReturn($policies);

  $action = new CalculateRefundAction($repo);

  // 5 ساعات لا تكفي لأي سياسة -> 0 استرجاع
  expect($action->execute($booking))->toBe(0.0);

  Carbon::setTestNow();
});

test('it returns 0 if no policies are defined for the resource', function () {
  $now = Carbon::create(2026, 5, 1, 12, 0, 0);
  Carbon::setTestNow($now);

  $booking = createFakeBooking(1, $now->copy()->addHours(48)->toDateTimeString(), 1000);

  $repo = Mockery::mock(BookingCancellationPolicyRepositoryInterface::class);
  $repo->shouldReceive('getPoliciesForResource')
    ->andReturn(collect([])); // لا توجد سياسات

  $action = new CalculateRefundAction($repo);

  expect($action->execute($booking))->toBe(0.0);

  Carbon::setTestNow();
});
