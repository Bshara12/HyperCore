<?php

use App\Domains\E_Commerce\Analytics\Actions\GetTopCustomersAction;
use App\Domains\E_Commerce\Analytics\DTOs\AnalyticsFilterDTO;
use App\Domains\E_Commerce\Analytics\Repositories\AnalyticsRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches top customers and stores them in cache', function () {
  $projectId = 1;
  $limit = 5;

  // إنشاء الـ DTO مع تمرير القيم في الـ Constructor
  $dto = new AnalyticsFilterDTO(
    from: '2026-05-01',
    to: '2026-05-10',
    period: 'daily',
    projectId: $projectId,
    limit: $limit
  );

  $mockData = [
    ['customer_id' => 101, 'name' => 'John Doe', 'total_spent' => 1500],
    ['customer_id' => 102, 'name' => 'Jane Smith', 'total_spent' => 1200],
  ];

  // إنشاء Mock للـ Repository
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);

  // التأكد من استدعاء التابع مرة واحدة فقط لاختبار فاعلية الكاش
  $repository->shouldReceive('getTopCustomers')
    ->once()
    ->with($dto)
    ->andReturn($mockData);

  $action = new GetTopCustomersAction($repository);

  // التنفيذ الأول (تعبئة الكاش)
  $result1 = $action->execute($dto);

  // التنفيذ الثاني (القراءة من الكاش)
  $result2 = $action->execute($dto);

  // التحقق من صحة البيانات والعملية
  expect($result1)->toBe($mockData);
  expect($result2)->toBe($mockData);

  // التحقق من صياغة مفتاح الكاش الصحيحة (يجب أن يحتوي على top_customers والـ limit)
  $cacheKey = "analytics:ecommerce:project:{$projectId}:top_customers:{$limit}:{$dto->from}:{$dto->to}";
  expect(Cache::has($cacheKey))->toBeTrue();
});

it('creates different cache keys when the limit changes', function () {
  $repository = Mockery::mock(AnalyticsRepositoryInterface::class);
  $repository->shouldReceive('getTopCustomers')->andReturn([]);

  $action = new GetTopCustomersAction($repository);

  // طلب أفضل 5 عملاء
  $dto5 = new AnalyticsFilterDTO('2026-05-01', '2026-05-10', 'daily', 1, 5);
  // طلب أفضل 10 عملاء
  $dto10 = new AnalyticsFilterDTO('2026-05-01', '2026-05-10', 'daily', 1, 10);

  $action->execute($dto5);
  $action->execute($dto10);

  expect(Cache::has("analytics:ecommerce:project:1:top_customers:5:2026-05-01:2026-05-10"))->toBeTrue();
  expect(Cache::has("analytics:ecommerce:project:1:top_customers:10:2026-05-01:2026-05-10"))->toBeTrue();
});
