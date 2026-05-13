<?php

namespace Tests\Unit\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\Actions\GetBookingOverviewAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it returns overview data from repository and caches it', function () {
  // 1. تجهيز الـ DTO والبيانات الوهمية
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-08',
    period: 'daily',
    projectId: 1,
    limit: 10
  );

  $mockData = [
    'bookings' => ['total' => 10],
    'resources' => ['total' => 5]
  ];

  // 2. إنشاء Mock للـ Repository
  // نريد التأكد أن التابع getOverview سيُستدعى مرة واحدة فقط
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getOverview')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetBookingOverviewAction($repository);

  // 3. التنفيذ للمرة الأولى (يجب أن يسحب من الـ Repository)
  $result1 = $action->execute($dto);

  // 4. التنفيذ للمرة الثانية (يجب أن يسحب من الـ Cache ولا يستدعي الـ Repository مرة أخرى)
  $result2 = $action->execute($dto);

  // التحققات (Assertions)
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التأكد أن الـ Cache يحتوي على القيمة
  $cacheKey = "analytics:booking:project:1:overview:2026-05-01:2026-05-08";
  expect(Cache::has($cacheKey))->toBeTrue();
});

test('it uses the correct cache key format', function () {
  $dto = new AnalyticsFilterDTO('2026-01-01', '2026-01-02', 'daily', 99, 10);

  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldIgnoreMissing();

  $action = new GetBookingOverviewAction($repository);
  $action->execute($dto);

  $expectedKey = "analytics:booking:project:99:overview:2026-01-01:2026-01-02";
  expect(Cache::has($expectedKey))->toBeTrue();
});
