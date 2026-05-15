<?php

namespace Tests\Unit\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Services\OrderService;
use App\Domains\E_Commerce\Actions\Order\AdminListOrdersAction;
use App\Domains\E_Commerce\Actions\Order\CreateOrderFromCartAction;
use App\Domains\E_Commerce\Actions\Order\EnrichOrderItemsAction;
use App\Domains\E_Commerce\Actions\Order\GetOrderDetailsAction;
use App\Domains\E_Commerce\Actions\Order\ListOrdersAction;
use App\Domains\E_Commerce\Actions\Order\UpdateOrderStatusAction;
use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\DTOs\Order\UpdateOrderStatusDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use Illuminate\Support\Collection;
use App\Models\Order;
use Mockery;

beforeEach(function () {
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->createFromCartAction = Mockery::mock(CreateOrderFromCartAction::class);
  $this->listOrdersAction = Mockery::mock(ListOrdersAction::class);
  $this->enrichOrderItemsAction = Mockery::mock(EnrichOrderItemsAction::class);
  $this->adminListOrdersAction = Mockery::mock(AdminListOrdersAction::class);
  $this->getOrderDetailsAction = Mockery::mock(GetOrderDetailsAction::class);
  $this->updateOrderStatusAction = Mockery::mock(UpdateOrderStatusAction::class);

  $this->service = new OrderService(
    $this->orderRepo,
    $this->createFromCartAction,
    $this->listOrdersAction,
    $this->enrichOrderItemsAction,
    $this->adminListOrdersAction,
    $this->getOrderDetailsAction,
    $this->updateOrderStatusAction
  );
});

afterEach(function () {
  Mockery::close();
});

it('creates an order from cart', function () {
  $dto = Mockery::mock(CreateOrderDTO::class);
  $this->createFromCartAction->shouldReceive('execute')->once()->with($dto)->andReturn(['id' => 1]);

  expect($this->service->createFromCart($dto))->toBe(['id' => 1]);
});

it('gets basic order info from repository', function () {
  // إنشاء كائن وهمي من نوع Order ليتوافق مع Return Type الخاص بالـ Repository
  $orderMock = Mockery::mock(Order::class);

  $this->orderRepo->shouldReceive('findByIdForUser')
    ->once()
    ->with(1, 10, 100)
    ->andReturn($orderMock); // إرجاع الكائن بدلاً من المصفوفة

  $result = $this->service->getOrder(1, 10, 100);

  expect($result)->toBeInstanceOf(Order::class);
});

it('lists orders for a user and enriches them', function () {
  $orders = collect([['id' => 1], ['id' => 2]]);

  $this->listOrdersAction->shouldReceive('execute')->once()->with(10, 100)->andReturn($orders);
  $this->enrichOrderItemsAction->shouldReceive('execute')->once()->with($orders)->andReturn($orders);

  $result = $this->service->listOrders(10, 100);
  expect($result)->toBe($orders);
});

it('lists admin orders with filters and enriches them', function () {
  $orders = collect([['id' => 1]]);
  $filters = ['status' => 'pending'];

  $this->adminListOrdersAction->shouldReceive('execute')->once()->with(10, $filters)->andReturn($orders);
  $this->enrichOrderItemsAction->shouldReceive('execute')->once()->with($orders)->andReturn($orders);

  $result = $this->service->adminListOrders(10, $filters);
  expect($result)->toBe($orders);
});

it('gets enriched order details', function () {
  $order = ['id' => 1, 'items' => []];

  $this->getOrderDetailsAction->shouldReceive('execute')
    ->once()
    ->with(1, 10, 100)
    ->andReturn($order);

  // الخدمة تقوم بتحويل الـ order الفردي إلى collection قبل تمريره للـ enrich
  $this->enrichOrderItemsAction->shouldReceive('execute')
    ->once()
    ->with(Mockery::on(function ($argument) use ($order) {
      return $argument instanceof Collection && $argument->first() === $order;
    }))
    ->andReturn(collect([$order]));

  $result = $this->service->getOrderDetails(1, 10, 100);
  expect($result)->toBe($order);
});

it('updates order status using a DTO', function () {
  $this->updateOrderStatusAction->shouldReceive('execute')
    ->once()
    ->with(Mockery::type(UpdateOrderStatusDTO::class))
    ->andReturn(true);

  $result = $this->service->updateOrderStatus(1, 10, 'completed');
  expect($result)->toBeTrue();
});
