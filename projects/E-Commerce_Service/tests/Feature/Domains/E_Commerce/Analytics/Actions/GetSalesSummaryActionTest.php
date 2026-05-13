<?php

use App\Domains\E_Commerce\Analytics\Actions\GetSalesSummaryAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches sales summary and caches the result', function () {
  $projectId = 1;

  // إنشاء الـ DTO وتمرير القيم مباشرة لتجنب خطأ readonly
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-10',
    period: 'daily',
    projectId: $projectId,
    limit: 10
  );

  $mockData = [
    'total_sales' => 5000,
    'orders_count' => 50,
    'average_order_value' => 100
  ];

  // إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);

  // نتحقق من أن المستودع يُستدعى مرة واحدة فقط (مرة للكاش ومرة للقراءة منه)
  $repository->shouldReceive('getSalesSummary')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetSalesSummaryAction($repository);

  // التنفيذ الأول: يملأ الكاش
  $result1 = $action->execute($dto);

  // التنفيذ الثاني: يسترجع من الكاش (لن يستدعي الـ Repository)
  $result2 = $action->execute($dto);

  // التحقق من النتائج
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التحقق من صياغة مفتاح الكاش الصحيحة لهذا الـ Action
  $cacheKey = "analytics:ecommerce:project:{$projectId}:sales_summary:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue();
});

it('ensures different date ranges create different cache keys', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getSalesSummary')->andReturn([]);

  $action = new GetSalesSummaryAction($repository);

  $dto1 = new AnalyticsFilterDTO('2026-01-01', '2026-01-15', 'daily', 1, 10);
  $dto2 = new AnalyticsFilterDTO('2026-02-01', '2026-02-15', 'daily', 1, 10);

  $action->execute($dto1);
  $action->execute($dto2);

  expect(Cache::has("analytics:ecommerce:project:1:sales_summary:2026-01-01:2026-01-15"))->toBeTrue();
  expect(Cache::has("analytics:ecommerce:project:1:sales_summary:2026-02-01:2026-02-15"))->toBeTrue();
});
