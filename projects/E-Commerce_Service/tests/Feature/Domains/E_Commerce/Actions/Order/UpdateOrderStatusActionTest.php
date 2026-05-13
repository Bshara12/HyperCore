<?php

use App\Domains\E_Commerce\Actions\Order\UpdateOrderStatusAction;
use App\Domains\E_Commerce\DTOs\Order\UpdateOrderStatusDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->action = new UpdateOrderStatusAction($this->orderRepo);
});

it('updates order status and flushes cache successfully', function () {
  Cache::shouldReceive('forget')->twice();
  Cache::shouldReceive('tags')->andReturnSelf();
  Cache::shouldReceive('flush')->once();

  $dto = new UpdateOrderStatusDTO(order_id: 1, project_id: 10, status: 'paid');

  // Mock Order Model
  $order = Mockery::mock(Order::class)->makePartial();
  $order->id = 1;
  $order->project_id = 10;
  $order->user_id = 100;
  $order->status = 'pending';

  $this->orderRepo->shouldReceive('findById')->with(1)->andReturn($order);

  // التوقع: تحديث الطلب وتحديث الـ Items التابعة له
  $order->shouldReceive('update')->once()->with(['status' => 'paid']);
  $this->orderRepo->shouldReceive('updateItemsStatus')->once()->with(1, 'paid');

  $result = $this->action->execute($dto);

  expect($result)->toBe($order);
});

it('throws exception if order does not exist or project_id mismatch', function () {
  $dto = new UpdateOrderStatusDTO(order_id: 99, project_id: 10, status: 'paid');

  // حالة عدم الوجود
  $this->orderRepo->shouldReceive('findById')->andReturn(null);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Order not found');

  // حالة اختلاف الـ project_id
  $order = Mockery::mock(Order::class)->makePartial();
  $order->project_id = 55; // مشروع مختلف
  $this->orderRepo->shouldReceive('findById')->andReturn($order);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Order not found');
});

it('throws exception for invalid status transitions', function () {
  // محاولة الانتقال من Delivered إلى أي حالة أخرى (ممنوع حسب المصفوفة)
  $dto = new UpdateOrderStatusDTO(order_id: 1, project_id: 10, status: 'pending');

  $order = Mockery::mock(Order::class)->makePartial();
  $order->project_id = 10;
  $order->status = 'delivered';

  $this->orderRepo->shouldReceive('findById')->andReturn($order);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, "Invalid status transition from delivered to pending");
});

it('allows allowed transitions', function (string $from, string $to) {
  $dto = new UpdateOrderStatusDTO(order_id: 1, project_id: 10, status: $to);

  // الـ Mock هنا يحتاج لـ user_id و project_id لأن الـ Action يستخدمهم لمسح الكاش
  $order = Mockery::mock(Order::class)->makePartial();
  $order->id = 1;
  $order->user_id = 100; // تأكد من وجود القيمة هنا
  $order->project_id = 10;
  $order->status = $from;

  $this->orderRepo->shouldReceive('findById')->with(1)->andReturn($order);

  // محاكاة التحديثات
  $order->shouldReceive('update')->once()->with(['status' => $to]);
  $this->orderRepo->shouldReceive('updateItemsStatus')->once()->with(1, $to);

  // معالجة الكاش لتجنب أخطاء الـ Type Hint
  Cache::shouldReceive('forget')->atLeast()->twice();
  Cache::shouldReceive('tags')->andReturnSelf();
  Cache::shouldReceive('flush');

  $this->action->execute($dto);
})->with([
  ['pending', 'paid'],
  ['paid', 'shipped'],
  ['shipped', 'delivered'],
  ['pending', 'cancelled'],
]);
