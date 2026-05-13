<?php

use App\Domains\E_Commerce\Actions\Order\CreateOrderFromCartAction;
use App\Domains\E_Commerce\Actions\Pricing\EnrichEntriesWithPricesAction;
use App\Domains\E_Commerce\Actions\Pricing\FetchEntriesByIdsAction;
use App\Domains\E_Commerce\DTOs\Order\CreateOrderDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Order\OrderItemRepositoryInterface;
use App\Models\Cart;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Fluent;

beforeEach(function () {
  $this->orderRepo = Mockery::mock(OrderRepositoryInterface::class);
  $this->orderItemRepo = Mockery::mock(OrderItemRepositoryInterface::class);
  $this->cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $this->fetchEntries = Mockery::mock(FetchEntriesByIdsAction::class);
  $this->enrichPrices = Mockery::mock(EnrichEntriesWithPricesAction::class);

  $this->action = new CreateOrderFromCartAction(
    $this->orderRepo,
    $this->orderItemRepo,
    $this->cartRepo,
    $this->fetchEntries,
    $this->enrichPrices
  );
});

// 1. اختبار النجاح (Happy Path)
it('creates an order successfully from cart', function () {
  Cache::shouldReceive('forget')->twice();
  Cache::shouldReceive('tags')->andReturnSelf();
  Cache::shouldReceive('flush')->once();

  $dto = new CreateOrderDTO(project_id: 1, user_id: 10, cart_id: 500, address: ['city' => 'Dubai']);

  // Mock Cart
  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->id = 500;
  $cart->user_id = 10;
  $cart->items = collect([
    new Fluent(['item_id' => 1, 'quantity' => 2])
  ]);

  $this->cartRepo->shouldReceive('findById')->with(500)->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  // Mock CMS Entries
  $entries = [['id' => 1, '0' => 'Product A', '3' => 10]]; // 3 هو الـ stock
  $this->fetchEntries->shouldReceive('execute')->andReturn($entries);

  // Mock Pricing
  $enriched = [['id' => 1, '0' => 'Product A', '3' => 10, 'final_price' => 100]];
  $this->enrichPrices->shouldReceive('execute')->andReturn($enriched);

  // Mock Order Creation
  $order = Mockery::mock(Order::class)->makePartial();
  $order->id = 99;
  $this->orderRepo->shouldReceive('create')->once()->andReturn($order);
  $this->orderItemRepo->shouldReceive('create')->once();
  $this->cartRepo->shouldReceive('delete')->with(500)->once();
  $this->orderRepo->shouldReceive('loadItems')->andReturn($order);

  $result = $this->action->execute($dto);

  expect($result->id)->toBe(99);
});

// 2. اختبار: السلة فارغة
it('throws exception if cart is empty', function () {
  $dto = new CreateOrderDTO(1, 10, 500, []);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->items = collect([]); // فارغة

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Cart is empty or not found');
});

// 3. اختبار: سلة غير مصرح بها (User ID mismatch)
it('throws exception for unauthorized cart access', function () {
  $dto = new CreateOrderDTO(1, 10, 500, []);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 99; // مستخدم مختلف
  $cart->items = collect([new Fluent()]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Unauthorized cart');
});

// 4. اختبار: المنتج غير موجود في CMS
it('throws exception if item is not found in CMS', function () {
  $dto = new CreateOrderDTO(1, 10, 500, []);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 10;
  $cart->items = collect([new Fluent(['item_id' => 999, 'quantity' => 1])]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  $this->fetchEntries->shouldReceive('execute')->andReturn([]); // مصفوفة فارغة من CMS
  $this->enrichPrices->shouldReceive('execute')->andReturn([]);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, 'Item not found in CMS: 999');
});

// 5. اختبار: الكمية المطلوبة أكبر من المخزون
it('throws exception if requested quantity exceeds stock', function () {
  $dto = new CreateOrderDTO(1, 10, 500, []);

  $cart = Mockery::mock(Cart::class)->makePartial();
  $cart->user_id = 10;
  $cart->items = collect([new Fluent(['item_id' => 1, 'quantity' => 5])]);

  $this->cartRepo->shouldReceive('findById')->andReturn($cart);
  $this->cartRepo->shouldReceive('loadItems')->andReturn($cart);

  $entries = [['id' => 1, '0' => 'Laptop', '3' => 2]]; // المتاح 2 فقط
  $this->fetchEntries->shouldReceive('execute')->andReturn($entries);
  $this->enrichPrices->shouldReceive('execute')->andReturn([
    ['id' => 1, '0' => 'Laptop', '3' => 2, 'final_price' => 500]
  ]);

  expect(fn() => $this->action->execute($dto))
    ->toThrow(\Exception::class, "Product 'Laptop' only has 2 items available, you requested 5");
});
