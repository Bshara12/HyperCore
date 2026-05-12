<?php

use App\Domains\E_Commerce\Actions\Order\GetOrderDetailsAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Models\Order; // استيراد الموديل
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->action = new GetOrderDetailsAction($this->orderRepo);

  Cache::flush();
});

it('returns order details and stores them in cache', function () {
  $orderId = 1;
  $projectId = 10;
  $userId = 100;

  // الحل: عمل Mock للموديل Order ليتوافق مع الـ Return Type الخاص بالـ Repository
  $mockOrder = Mockery::mock(Order::class)->makePartial();
  $mockOrder->id = $orderId;
  $mockOrder->status = 'pending';

  $this->orderRepo->shouldReceive('findDetailedForUser')
    ->once() // نؤكد أنه يستدعى مرة واحدة فقط (لإثبات عمل الكاش)
    ->with($orderId, $projectId, $userId)
    ->andReturn($mockOrder);

  // التنفيذ الأول (Miss - يذهب للمستودع)
  $result1 = $this->action->execute($orderId, $projectId, $userId);

  // التنفيذ الثاني (Hit - يأخذ من الكاش)
  $result2 = $this->action->execute($orderId, $projectId, $userId);

  expect($result1->id)->toBe($orderId);
  expect($result2->id)->toBe($orderId);
  expect(Cache::has(CacheKeys::order($orderId, $userId)))->toBeTrue();
});

it('throws exception if order is not found', function () {
  $orderId = 999;
  $projectId = 10;
  $userId = 100;

  // هنا نرجع null وهي مسموحة حسب الـ Interface (?Order)
  $this->orderRepo->shouldReceive('findDetailedForUser')
    ->once()
    ->andReturn(null);

  expect(fn() => $this->action->execute($orderId, $projectId, $userId))
    ->toThrow(\Exception::class, 'Order not found');
});
