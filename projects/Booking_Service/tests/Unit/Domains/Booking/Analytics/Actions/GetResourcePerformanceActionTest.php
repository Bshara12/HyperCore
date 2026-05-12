<?php

namespace Tests\Unit\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\Actions\GetResourcePerformanceAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it returns resource performance data from repository and caches it', function () {
  // 1. تجهيز البيانات والـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-05',
    period: 'daily',
    projectId: 1,
    limit: 10
  );

  $mockData = [
    'resources' => [
      ['resource_id' => 1, 'occupancy_rate' => 75.0, 'total_revenue' => 1000.0]
    ]
  ];

  // 2. إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getResourcePerformance')
    ->once() // التأكد من أن الكاش سيمنع الاستدعاء الثاني
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetResourcePerformanceAction($repository);

  // 3. التنفيذ مرتين
  $result1 = $action->execute($dto);
  $result2 = $action->execute($dto);

  // 4. التحققات (Assertions)
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التحقق من صحة مفتاح الكاش
  $expectedKey = "analytics:booking:project:1:resource_performance:2026-05-01:2026-05-05";
  expect(Cache::has($expectedKey))->toBeTrue();
});
