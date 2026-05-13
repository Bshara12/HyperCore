<?php

use App\Domains\E_Commerce\Analytics\Actions\GetTopProductsAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches top products and caches the result', function () {
  $projectId = 1;
  $limit = 10;

  // إنشاء الـ DTO وتمرير القيم في الـ Constructor مباشرة
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-10',
    period: 'daily',
    projectId: $projectId,
    limit: $limit
  );

  $mockData = [
    'top_by_quantity' => [
      ['product_id' => 1, 'name' => 'Product A', 'total_quantity' => 100],
    ],
    'top_by_revenue' => [
      ['product_id' => 2, 'name' => 'Product B', 'total_revenue' => 5000],
    ]
  ];

  // إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);

  // التأكد من استدعاء التابع مرة واحدة فقط لاختبار فاعلية الكاش
  $repository->shouldReceive('getTopProducts')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetTopProductsAction($repository);

  // التنفيذ الأول (تعبئة الكاش)
  $result1 = $action->execute($dto);

  // التنفيذ الثاني (استرجاع من الكاش)
  $result2 = $action->execute($dto);

  // التحقق من صحة البيانات والعملية
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التحقق من صياغة مفتاح الكاش (يجب أن يحتوي على top_products والـ limit)
  $cacheKey = "analytics:ecommerce:project:{$projectId}:top_products:{$limit}:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue();
});

it('generates different keys for different limits in top products', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getTopProducts')->andReturn([]);

  $action = new GetTopProductsAction($repository);

  $dto5 = new AnalyticsFilterDTO('2026-05-01', '2026-05-10', 'daily', 1, 5);
  $dto20 = new AnalyticsFilterDTO('2026-05-01', '2026-05-10', 'daily', 1, 20);

  $action->execute($dto5);
  $action->execute($dto20);

  expect(Cache::has("analytics:ecommerce:project:1:top_products:5:2026-05-01:2026-05-10"))->toBeTrue();
  expect(Cache::has("analytics:ecommerce:project:1:top_products:20:2026-05-01:2026-05-10"))->toBeTrue();
});
