<?php

namespace Tests\Unit\Domains\Booking\Actions\Client;

use App\Domains\Booking\Actions\Client\CreateBookingRecordAction;
use App\Domains\Booking\DTOs\Client\CreateBookingDTO;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it creates a booking record and clears relevant cache', function () {
  // 1. تجهيز البيانات والـ DTO
  $dto = new CreateBookingDTO(
    resourceId: 1,
    userId: 10,
    userName: 'John Doe',
    projectId: 100,
    startAt: '2026-05-10 10:00:00',
    endAt: '2026-05-10 11:00:00',
    amount: 150.0,
    currency: 'USD',
    gateway: 'stripe',
    gatewayToken: 'tok_test_123',
  );

  $bookingMock = new Booking(['id' => 500]);

  // 2. محاكاة الـ Cache
  // نضع بيانات في الكاش للتأكد من حذفها لاحقاً
  $userBookingsKey = CacheKeys::userBookings($dto->userId);
  Cache::put($userBookingsKey, 'some_data');

  // ملاحظة: Cache::tags يحتاج لمحاكاة دقيقة أو استخدامه فعلياً مع driver 'array'
  // بما أن Pest يستخدم array driver، الكود سيعمل بشكل طبيعي
  Cache::tags(["resource_{$dto->resourceId}_bookings"])->put('list', 'data');

  // 3. محاكاة المستودع
  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('create')
    ->once()
    ->with([
      'resource_id' => $dto->resourceId,
      'user_id' => $dto->userId,
      'project_id' => $dto->projectId,
      'start_at' => $dto->startAt,
      'end_at' => $dto->endAt,
      'status' => Booking::STATUS_PENDING,
      'amount' => $dto->amount,
      'currency' => $dto->currency,
    ])
    ->andReturn($bookingMock);

  $action = new CreateBookingRecordAction($repository);

  // 4. التنفيذ
  $result = $action->execute($dto);

  // 5. التحققات (Assertions)
  expect($result)->toBeInstanceOf(Booking::class);

  // تحقق من حذف كاش المستخدم
  expect(Cache::has($userBookingsKey))->toBeFalse('User bookings cache should be cleared');

  // تحقق من تطهير الـ Tags (في الـ array driver، استرجاع القيمة بعد flush يعطي null)
  expect(Cache::tags(["resource_{$dto->resourceId}_bookings"])->get('list'))->toBeNull();
});
