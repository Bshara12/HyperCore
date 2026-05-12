<?php

namespace Tests\Unit\Domains\Booking\Read\Actions;

use App\Domains\Booking\Read\Actions\GetResourceBookingsAction;
use App\Domains\Booking\Read\DTOs\GetResourceBookingsDTO;
use App\Domains\Booking\Repositories\Interface\BookingRepositoryInterface;
use App\Domains\Booking\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase; // <--- أضف هذا
use Mockery;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

// نستخدم TestCase ونضيف RefreshDatabase لإنشاء الجداول المطلوبة
uses(RefreshDatabase::class);

beforeEach(function () {
  // تأكد من استخدام درايفر يدعم التاجات
  config(['cache.default' => 'array']);
});

// ملاحظة: تأكد أن config('cache.default') هو 'array' في ملف phpunit.xml لنجاح Tags
test('it returns resource bookings from repository and uses cache tags', function () {
  // 1. تجهيز الـ DTO
  $resourceId = 101;
  $dto = new GetResourceBookingsDTO(
    resourceId: $resourceId,
    status: 'confirmed',
    from: '2026-05-01',
    to: '2026-05-10'
  );

  $mockBookings = new EloquentCollection([['id' => 1, 'status' => 'confirmed']]);

  // 2. إنشاء Mock للـ Repository
  $repository = Mockery::mock(BookingRepositoryInterface::class);
  $repository->shouldReceive('listByResource')
    ->once() // التأكد من استدعاء قاعدة البيانات مرة واحدة
    ->with($resourceId, 'confirmed', '2026-05-01', '2026-05-10')
    ->andReturn($mockBookings);

  $action = new GetResourceBookingsAction($repository);

  // 3. التنفيذ الأول
  $result1 = $action->execute($dto);

  // ... الكود السابق
  $result2 = $action->execute($dto);

  expect($result1)->toBe($mockBookings);
  expect($result2)->toBe($mockBookings);

  // الحل الأنظف: التأكد أن الكاش يحتوي على التاج والمفتاح الصحيح برمجياً
  $filtersHash = md5(json_encode([
    'status' => $dto->status,
    'from' => $dto->from,
    'to' => $dto->to,
  ]));

  $expectedKey = CacheKeys::resourceBookings($resourceId, $filtersHash);

  expect(Cache::tags(["resource_{$resourceId}_bookings"])->get($expectedKey))->not->toBeNull();
});

test('it generates unique cache keys based on filters hash', function () {
  // 1. إنشاء الـ Mock
  $repository = Mockery::mock(BookingRepositoryInterface::class);

  // 2. تحديد التوقعات مسبقاً (Expectation)
  // نتوقع أن يتم استدعاؤه مرتين بالضبط
  $repository->shouldReceive('listByResource')
    ->times(2)
    ->andReturn(new EloquentCollection());

  $action = new GetResourceBookingsAction($repository);

  $dto1 = new GetResourceBookingsDTO(101, 'confirmed', '2026-05-01', '2026-05-10');
  $dto2 = new GetResourceBookingsDTO(101, 'completed', '2026-05-01', '2026-05-10');

  $action->execute($dto1);
  $action->execute($dto2);
});
