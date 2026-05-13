<?php

use App\Domains\E_Commerce\Actions\Order\AdminListOrdersAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use Illuminate\Support\Facades\Cache;

it('fetches admin orders and caches them using tags and filter hash', function () {
  // 1. إعداد البيانات
  $projectId = 1;
  $filters = ['status' => 'pending', 'limit' => 10];
  $filtersHash = md5(json_encode($filters));

  $mockOrders = collect([
    ['id' => 1, 'total' => 150.00],
    ['id' => 2, 'total' => 200.00]
  ]);

  // 2. بناء الـ Mock للـ Repository
  $orderRepo = Mockery::mock(OrderRepositoryInterface::class);

  // نتوقع استدعاء جلب الطلبات بالبارامترات المحددة
  $orderRepo->shouldReceive('getAllOrders')
    ->once()
    ->with($projectId, $filters)
    ->andReturn($mockOrders);

  // 3. محاكاة الكاش (Tags & Remember)
  // ملاحظة: يجب أن يكون Cache Driver يدعم الـ Tags (مثل array في الاختبارات)
  Cache::shouldReceive('tags')
    ->once()
    ->with(['admin_orders'])
    ->andReturnSelf();

  Cache::shouldReceive('remember')
    ->once()
    ->with(
      Mockery::on(fn($key) => str_contains($key, (string)$projectId) && str_contains($key, $filtersHash)),
      Mockery::any(),
      Mockery::type('Closure')
    )
    ->andReturnUsing(fn($key, $ttl, $callback) => $callback());

  $action = new AdminListOrdersAction($orderRepo);

  // 4. التنفيذ
  $result = $action->execute($projectId, $filters);

  // 5. التحقق
  expect($result)->toBe($mockOrders);
  expect($result)->toHaveCount(2);
});

it('generates a unique cache key based on filters', function () {
  $projectId = 1;
  $filters1 = ['status' => 'paid'];
  $filters2 = ['status' => 'cancelled'];

  $orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $orderRepo->shouldReceive('getAllOrders')->twice()->andReturn(collect());

  // محاكاة الكاش للتحقق من المفاتيح المختلفة
  Cache::shouldReceive('tags')->andReturnSelf();

  $keys = [];
  Cache::shouldReceive('remember')
    ->twice()
    ->andReturnUsing(function ($key, $ttl, $callback) use (&$keys) {
      $keys[] = $key;
      return $callback();
    });

  $action = new AdminListOrdersAction($orderRepo);

  $action->execute($projectId, $filters1);
  $action->execute($projectId, $filters2);

  // التحقق من أن المفاتيح مختلفة بسبب اختلاف الـ Filters
  expect($keys[0])->not->toBe($keys[1]);
});
