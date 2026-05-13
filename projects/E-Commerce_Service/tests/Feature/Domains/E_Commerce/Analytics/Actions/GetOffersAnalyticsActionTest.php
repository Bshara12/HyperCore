<?php

use App\Domains\E_Commerce\Analytics\Actions\GetOffersAnalyticsAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches offers analytics and caches the result', function () {
  // 1. إعداد الـ DTO
  $projectId = 1;
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-10',
    to: '2026-05-11',
    period: 'daily',
    projectId: $projectId,
    limit: 10
  );

  $mockData = [
    'summary' => ['total_offers' => 5],
    'top_offers' => []
  ];

  // 2. إنشاء Mock للـ Repository باستخدام Mockery مدمج مع Pest
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);

  // نضمن استدعاء التابع مرة واحدة فقط للتأكد من أن المرة الثانية ستأتي من الكاش
  $repository->shouldReceive('getOffersAnalytics')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetOffersAnalyticsAction($repository);

  // 3. التنفيذ الأول (يملأ الكاش)
  $result1 = $action->execute($dto);

  // 4. التنفيذ الثاني (يجب أن يقرأ من الكاش)
  $result2 = $action->execute($dto);

  // التحقق من النتائج (تصحيح toBeTrue)
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  $cacheKey = "analytics:ecommerce:project:{$projectId}:offers:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue(); // تصحيح T الكبيرة
});

it('uses the correct cache key format', function () {
  $projectId = 7;
  $from = '2026-01-01';
  $to = '2026-01-07';

  $dto = new AnalyticsFilterDTO($from, $to, 'daily', $projectId, 10);

  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getOffersAnalytics')->andReturn([]);

  $action = new GetOffersAnalyticsAction($repository);
  $action->execute($dto);

  $expectedKey = "analytics:ecommerce:project:7:offers:2026-01-01:2026-01-07";
  expect(Cache::has($expectedKey))->toBeTrue(); // تصحيح T الكبيرة
});
