<?php

namespace Tests\Unit\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\Actions\GetPeakTimesAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it returns peak times data from repository and caches it', function () {
  // 1. تجهيز البيانات والـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'daily', // الـ period لا يؤثر على مفتاح الكاش في هذا الـ Action
    projectId: 1,
    limit: 10
  );

  $mockPeakData = [
    'by_day_of_week' => [],
    'by_hour' => [],
    'avg_lead_time' => []
  ];

  // 2. إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getPeakTimes')
    ->once()
    ->with($dto)
    ->andReturn($mockPeakData);

  $action = new GetPeakTimesAction($repository);

  // 3. التنفيذ مرتين
  $result1 = $action->execute($dto);
  $result2 = $action->execute($dto);

  // 4. التحققات
  expect($result1)->toBe($mockPeakData);
  expect($result2)->toBe($mockPeakData);

  // التحقق من مفتاح الكاش (بدون period)
  $expectedKey = "analytics:booking:project:1:peak_times:2026-05-01:2026-05-31";
  expect(Cache::has($expectedKey))->toBeTrue();
});
