<?php

use App\Domains\E_Commerce\Analytics\Actions\GetSalesTrendAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches sales trend and caches the result', function () {
  $projectId = 1;

  // إنشاء الـ DTO مع تمرير القيم مباشرة في الـ Constructor
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-10',
    period: 'daily', // تأكد أن المسمى يطابق تعريف الـ DTO لديك (period أو groupBy)
    projectId: $projectId,
    limit: 10
  );

  $mockData = [
    ['date' => '2026-05-01', 'total_sales' => 150],
    ['date' => '2026-05-02', 'total_sales' => 200],
  ];

  // إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);

  // نضمن استدعاء التابع مرة واحدة فقط للتحقق من عمل الكاش
  $repository->shouldReceive('getSalesTrend')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetSalesTrendAction($repository);

  // التنفيذ الأول (تخزين في الكاش)
  $result1 = $action->execute($dto);

  // التنفيذ الثاني (استرجاع من الكاش)
  $result2 = $action->execute($dto);

  // التحقق من صحة النتائج
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التحقق من صياغة مفتاح الكاش (يجب أن يحتوي على sales_trend والـ period)
  $cacheKey = "analytics:ecommerce:project:{$projectId}:sales_trend:daily:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue();
});

it('uses different cache keys for different periods', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getSalesTrend')->andReturn([]);

  $action = new GetSalesTrendAction($repository);

  // DTO يومي
  $dtoDaily = new AnalyticsFilterDTO('2026-05-01', '2026-05-30', 'daily', 1, 10);
  // DTO شهري
  $dtoMonthly = new AnalyticsFilterDTO('2026-05-01', '2026-05-30', 'monthly', 1, 10);

  $action->execute($dtoDaily);
  $action->execute($dtoMonthly);

  expect(Cache::has("analytics:ecommerce:project:1:sales_trend:daily:2026-05-01:2026-05-30"))->toBeTrue();
  expect(Cache::has("analytics:ecommerce:project:1:sales_trend:monthly:2026-05-01:2026-05-30"))->toBeTrue();
});
