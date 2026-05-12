<?php

namespace Tests\Unit\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\Actions\GetBookingTrendAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

test('it returns trend data from repository and caches it with period in key', function () {
  // 1. تجهيز البيانات والـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-08',
    period: 'daily',
    projectId: 1,
    limit: 10
  );

  $mockTrendData = [
    'period' => 'daily',
    'data' => [
      ['label' => '2026-05-01', 'bookings_count' => 5],
      ['label' => '2026-05-02', 'bookings_count' => 3],
    ]
  ];

  // 2. إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getBookingTrend')
    ->once() // التأكد من استدعاء قاعدة البيانات مرة واحدة فقط
    ->with($dto)
    ->andReturn($mockTrendData);

  $action = new GetBookingTrendAction($repository);

  // 3. التنفيذ الأول (سحب من المستودع)
  $result1 = $action->execute($dto);

  // 4. التنفيذ الثاني (سحب من الكاش)
  $result2 = $action->execute($dto);

  // 5. التحققات
  expect($result1)->toBe($mockTrendData);
  expect($result2)->toBe($mockTrendData);

  // التحقق من أن مفتاح الكاش يحتوي على الـ period
  $expectedKey = "analytics:booking:project:1:trend:daily:2026-05-01:2026-05-08";
  expect(Cache::has($expectedKey))->toBeTrue();
});

test('it differentiates cache keys by period', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldIgnoreMissing();
  $action = new GetBookingTrendAction($repository);

  // نفس التاريخ ولكن period مختلف (يومي ضد شهري)
  $dtoDaily = new AnalyticsFilterDTO('2026-01-01', '2026-03-01', 'daily', 1, 10);
  $dtoMonthly = new AnalyticsFilterDTO('2026-01-01', '2026-03-01', 'monthly', 1, 10);

  $action->execute($dtoDaily);
  $action->execute($dtoMonthly);

  expect(Cache::has("analytics:booking:project:1:trend:daily:2026-01-01:2026-03-01"))->toBeTrue();
  expect(Cache::has("analytics:booking:project:1:trend:monthly:2026-01-01:2026-03-01"))->toBeTrue();
});
