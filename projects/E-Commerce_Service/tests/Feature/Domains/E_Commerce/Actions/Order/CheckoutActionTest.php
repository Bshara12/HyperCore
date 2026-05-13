<?php

use App\Domains\E_Commerce\Actions\Order\CheckoutAction;
use App\Domains\E_Commerce\Actions\Order\CalculateCartPricingAction;
use App\Domains\E_Commerce\Actions\Stock\UpdateStockInCMSAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Domains\Payment\Services\PaymentService;
use App\Domains\E_Commerce\DTOs\Order\CheckoutDTO;
use App\Models\Cart;
use App\Models\Order; // استيراد موديل الـ Order
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Fluent;

beforeEach(function () {
  $this->cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $this->pricingAction = Mockery::mock(CalculateCartPricingAction::class);
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->orderItemRepo = Mockery::mock(OrderItemRepositoryInterface::class);
  $this->paymentService = Mockery::mock(PaymentService::class);
  $this->updateStockAction = Mockery::mock(UpdateStockInCMSAction::class);

  $this->action = new CheckoutAction(
    $this->cartRepo,
    $this->pricingAction,
    $this->orderRepo,
    $this->orderItemRepo,
    $this->paymentService,
    $this->updateStockAction
  );
});

it('completes a successful checkout with online payment', function () {
  Event::fake();
  Cache::shouldReceive('forget')->atLeast()->times(3);
  Cache::shouldReceive('tags')->andReturnSelf();
  Cache::shouldReceive('flush')->once();

  $dto = new CheckoutDTO(
    project_id: 1,
    user_id: 10,
    user_name: 'John Doe',
    cart_id: 1,
    payment_method: 'online',
    gateway: 'stripe',
    payment_type: 'card',
    address: ['street' => 'Main St']
  );

  // Mock Cart Model
  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->id = 1;
  $cart->user_id = 10;
  $cart->items = collect([new Fluent(['item_id' => 101])]);

  $this->cartRepo->shouldReceive('findById')->with(1)->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->with($cart)->andReturn($cart);

  $pricing = [
    'total' => 200,
    'items' => [['product_id' => 101, 'quantity' => 2, 'count' => 10, 'price' => 50, 'total' => 100, 'title' => 'P1']]
  ];

  $this->pricingAction->shouldReceive('execute')->andReturn($pricing);
  $this->paymentService->shouldReceive('processPayment')->andReturn(['status' => 'paid']);
  $this->updateStockAction->shouldReceive('execute')->once();

  // الحل هنا: عمل Mock لموديل الـ Order بدلاً من Fluent
  $order = Mockery::mock(Order::class)->makePartial();
  $order->id = 50;

  $this->orderRepo->shouldReceive('create')->once()->andReturn($order);
  $this->orderItemRepo->shouldReceive('create')->once();
  $this->cartRepo->shouldReceive('delete')->once();
  $this->orderRepo->shouldReceive('loadItems')->with($order)->andReturn($order);

  $result = $this->action->execute($dto);

  expect($result->id)->toBe(50);
  Event::assertDispatched(SystemLogEvent::class);
});

it('throws exception if product is out of stock', function () {
  $dto = new CheckoutDTO(1, 10, 'John', 1, 'cod', null, null, ['a' => 'b']);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 10;
  $cart->items = collect([new Fluent()]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  $pricing = ['items' => [['product_id' => 101, 'quantity' => 5, 'count' => 2, 'title' => 'Laptop']]];
  $this->pricingAction->shouldReceive('execute')->andReturn($pricing);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Product Laptop only has 2 left');
});

it('throws exception for unauthorized cart access', function () {
  $dto = new CheckoutDTO(1, 10, 'John', 1, 'cod', null, null, ['a' => 'b']);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 99; // مستخدم مختلف
  $cart->items = collect([new Fluent()]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Unauthorized cart');
});

it('throws exception if payment fails', function () {
  $dto = new CheckoutDTO(1, 10, 'John', 1, 'online', 'stripe', 'card', ['a' => 'b']);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 10;
  $cart->items = collect([new Fluent()]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);
  $this->pricingAction->shouldReceive('execute')->andReturn(['total' => 100, 'items' => []]);

  $this->paymentService->shouldReceive('processPayment')->andReturn(['status' => 'failed']);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Payment failed');
});

it('throws exception if cart is empty', function () {
  $dto = new CheckoutDTO(
    project_id: 1,
    user_id: 10,
    user_name: 'John',
    cart_id: 1,
    payment_method: 'cod',
    gateway: null,
    payment_type: null,
    address: ['location' => 'Test Address']
  );

  // إنشاء Mock لسلة موجودة ولكن بدون عناصر (empty collection)
  $cart = Mockery::mock(App\Models\Cart::class)->makePartial();
  $cart->user_id = 10;
  $cart->items = collect([]); // سلة فارغة

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Cart is empty');
});
