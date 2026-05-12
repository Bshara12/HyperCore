<?php

use App\Domains\E_Commerce\Actions\Order\ListOrdersAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->action = new ListOrdersAction($this->orderRepo);

  // تنظيف الكاش لضمان عدم وجود تداخل بين الاختبارات
  Cache::flush();
});

it('lists user orders and stores them in cache', function () {
  $projectId = 1;
  $userId = 10;

  // إنشاء Mock لمجموعة من الطلبات باستخدام موديل Order لتجنب TypeError
  $order1 = Mockery::mock(Order::class)->makePartial();
  $order1->id = 101;

  $order2 = Mockery::mock(Order::class)->makePartial();
  $order2->id = 102;

  $mockOrders = collect([$order1, $order2]);

  // التوقع: استدعاء المستودع مرة واحدة فقط
  $this->orderRepo->shouldReceive('getUserOrders')
    ->once()
    ->with($projectId, $userId)
    ->andReturn($mockOrders);

  // التنفيذ للمرة الأولى (Cache Miss)
  $result1 = $this->action->execute($projectId, $userId);

  // التنفيذ للمرة الثانية (Cache Hit)
  $result2 = $this->action->execute($projectId, $userId);

  // التحقق من صحة البيانات
  expect($result1)->toHaveCount(2);
  expect($result1->first()->id)->toBe(101);
  expect($result2)->toHaveCount(2);

  // التحقق من أن الكاش يحتوي فعلياً على المفتاح الصحيح
  expect(Cache::has(CacheKeys::userOrders($userId, $projectId)))->toBeTrue();
});

it('returns empty collection if user has no orders', function () {
  $projectId = 1;
  $userId = 10;

  $this->orderRepo->shouldReceive('getUserOrders')
    ->once()
    ->andReturn(collect([])); // إرجاع مجموعة فارغة

  $result = $this->action->execute($projectId, $userId);

  expect($result)->toBeEmpty();
  expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);
});
