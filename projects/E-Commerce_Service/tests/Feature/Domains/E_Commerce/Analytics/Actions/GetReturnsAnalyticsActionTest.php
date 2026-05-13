<?php

use App\Domains\E_Commerce\Analytics\Actions\GetReturnsAnalyticsAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches returns analytics and stores it in cache', function () use (&$repository) {
  $projectId = 1;

  // تمرير جميع القيم داخل الـ Constructor مباشرة
  // تأكد من ترتيب البرامترات حسب تعريف الـ DTO لديك
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-10',
    period: 'daily', // مررها هنا مباشرة بدلاً من التعيين الخارجي
    projectId: $projectId,
    limit: 10
  );

  $mockData = [
    'total_returned_orders' => 5,
    'return_rate' => 2.5
  ];

  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getReturnsAnalytics')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetReturnsAnalyticsAction($repository);

  // التنفيذ
  $result = $action->execute($dto);

  expect($result)->toBe($mockData);

  // تأكد من مطابقة صياغة مفتاح الكاش المستخدمة في الـ Action
  $cacheKey = "analytics:ecommerce:project:{$projectId}:returns:daily:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue();
});

it('generates unique cache keys based on DTO parameters', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getReturnsAnalytics')->andReturn([]);

  $action = new GetReturnsAnalyticsAction($repository);

  // DTO الأول
  $dto1 = new AnalyticsFilterDTO('2026-01-01', '2026-01-02', 'daily', 1, 10);

  // DTO الثاني (مشروع مختلف)
  $dto2 = new AnalyticsFilterDTO('2026-01-01', '2026-01-02', 'daily', 2, 10);

  $action->execute($dto1);
  $action->execute($dto2);

  $key1 = "analytics:ecommerce:project:1:returns:daily:2026-01-01:2026-01-02";
  $key2 = "analytics:ecommerce:project:2:returns:daily:2026-01-01:2026-01-02";

  expect(Cache::has($key1))->toBeTrue();
  expect(Cache::has($key2))->toBeTrue();
});
