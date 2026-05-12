<?php

use App\Domains\E_Commerce\Actions\Cart\ClearCartAction;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Models\Cart;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('clears the cart items and forgets the cache when cart exists', function () {
  Event::fake();
  $projectId = 1;
  $userId = 10;

  // 1. إعداد موديبل حقيقي لتجنب تعارض الأنواع
  $mockCart = new Cart(['id' => 777]);

  // 2. بناء الـ Mocks
  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  // توقعات الـ Repository
  $cartRepo->shouldReceive('findByProjectAndUser')
    ->once()
    ->with($projectId, $userId)
    ->andReturn($mockCart);

  $cartItemRepo->shouldReceive('deleteByCartId')
    ->once()
    ->with($mockCart->id);

  $cartRepo->shouldReceive('loadItems')
    ->once()
    ->with($mockCart)
    ->andReturn(['items' => []]);

  // توقعات الكاش
  Cache::shouldReceive('forget')
    ->once();

  $action = new ClearCartAction($cartRepo, $cartItemRepo);

  // 3. التنفيذ والتحقق
  $result = $action->execute($projectId, $userId);

  expect($result)->toBeArray();
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($userId, $mockCart) {
    return $event->eventType === 'clear_cart' && $event->entityId === $mockCart->id;
  });
});

it('throws an exception if the cart is not found', function () {
  $projectId = 1;
  $userId = 99;

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  $cartRepo->shouldReceive('findByProjectAndUser')
    ->once()
    ->andReturn(null);

  $action = new ClearCartAction($cartRepo, $cartItemRepo);

  // التحقق من رمي الاستثناء لتغطية سطر الـ throw
  expect(fn() => $action->execute($projectId, $userId))
    ->toThrow(RuntimeException::class, 'Cart not found');
});
