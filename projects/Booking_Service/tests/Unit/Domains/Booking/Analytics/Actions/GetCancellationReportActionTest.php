<?php

namespace Tests\Unit\Domains\Booking\Analytics\Actions;

use App\Domains\Booking\Analytics\Actions\GetCancellationReportAction;
use App\Domains\Booking\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\Booking\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Mockery;

test('it returns cancellation report from repository and caches it', function () {
  // 1. تجهيز البيانات والـ DTO
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-31',
    period: 'monthly',
    projectId: 1,
    limit: 10
  );

  $mockReport = [
    'summary' => ['total_cancellations' => 5],
    'by_resource' => [],
    'trend' => []
  ];

  // 2. إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getCancellationReport')
    ->once() // نتحقق من استدعاء المستودع مرة واحدة فقط لضمان عمل الكاش
    ->with($dto)
    ->andReturn($mockReport);

  $action = new GetCancellationReportAction($repository);

  // 3. التنفيذ مرتين للتأكد من فاعلية الكاش
  $result1 = $action->execute($dto);
  $result2 = $action->execute($dto);

  // 4. التحققات
  expect($result1)->toBe($mockReport);
  expect($result2)->toBe($mockReport);

  // التحقق من مفتاح الكاش الصحيح
  $expectedKey = "analytics:booking:project:1:cancellations:monthly:2026-05-01:2026-05-31";
  expect(Cache::has($expectedKey))->toBeTrue();
});
