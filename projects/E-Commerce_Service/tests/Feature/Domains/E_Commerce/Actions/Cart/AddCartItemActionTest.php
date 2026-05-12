<?php

use App\Domains\E_Commerce\Actions\Cart\AddCartItemAction;
use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\CartItem;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('adds items to cart by updating existing or creating new ones', function () {
  Event::fake();

  // إعداد الـ DTO
  $dto = new AddCartItemsDTO(
    project_id: 1,
    user_id: 10,
    items: [
      ['item_id' => 101, 'quantity' => 2], // تحديث
      ['item_id' => 102, 'quantity' => 5], // إنشاء جديد
    ]
  );

  // 1. إنشاء موديبلات حقيقية (بدون حفظ) لتجنب TypeError
  $mockCart = new Cart(['id' => 500]);
  $existingCartItem = new CartItem(['id' => 1, 'quantity' => 1, 'cart_id' => 500, 'item_id' => 101]);

  // 2. بناء الـ Mocks
  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  // توقعات الـ CartRepository
  $cartRepo->shouldReceive('getOrCreate')
    ->once()
    ->with($dto->project_id, $dto->user_id)
    ->andReturn($mockCart);

  // حالة التحديث (Item 101)
  $cartItemRepo->shouldReceive('findByCartAndItem')
    ->with(500, 101)
    ->once()
    ->andReturn($existingCartItem);

  $cartItemRepo->shouldReceive('update')
    ->once()
    ->with($existingCartItem, ['quantity' => 3]); // 1 + 2

  // حالة الإضافة الجديدة (Item 102)
  $cartItemRepo->shouldReceive('findByCartAndItem')
    ->with(500, 102)
    ->once()
    ->andReturn(null);

  $cartItemRepo->shouldReceive('create')
    ->once()
    ->with([
      'cart_id'  => 500,
      'item_id'  => 102,
      'quantity' => 5,
    ]);

  // النهاية: مسح الكاش وتحميل العناصر
  Cache::shouldReceive('forget')->once();

  $cartRepo->shouldReceive('loadItems')
    ->once()
    ->with($mockCart)
    ->andReturn(['items' => []]);

  $action = new AddCartItemAction($cartRepo, $cartItemRepo);

  // 3. التنفيذ والتحقق
  $result = $action->execute($dto);

  Event::assertDispatched(SystemLogEvent::class);
  expect($result)->toBeArray();
});
