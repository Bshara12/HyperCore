<?php

use App\Domains\E_Commerce\Actions\Cart\UpdateCartItemsAction;
use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Models\Cart;
use App\Models\CartItem;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('updates, deletes, or skips cart items based on quantity and existence', function () {
  Event::fake();
  Cache::shouldReceive('forget')->once();

  $projectId = 1;
  $userId = 10;

  // 1. إعداد الـ DTO لجميع الحالات
  $dto = new UpdateCartItemsDTO(
    project_id: $projectId,
    user_id: $userId,
    items: [
      ['item_id' => 101, 'quantity' => 5],  // حالة: تحديث الكمية
      ['item_id' => 102, 'quantity' => 0],  // حالة: حذف العنصر
      ['item_id' => 103, 'quantity' => 10], // حالة: غير موجود (skip)
    ]
  );

  // 2. إعداد الموديبلات والـ Mocks
  $mockCart = new Cart(['id' => 500]);
  $existingItem1 = new CartItem(['id' => 1, 'item_id' => 101, 'cart_id' => 500]);
  $existingItem2 = new CartItem(['id' => 2, 'item_id' => 102, 'cart_id' => 500]);

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  $cartRepo->shouldReceive('findByProjectAndUser')->once()->andReturn($mockCart);

  // توقعات العنصر الأول (Update)
  $cartItemRepo->shouldReceive('findByCartAndItem')->with(500, 101)->once()->andReturn($existingItem1);
  $cartItemRepo->shouldReceive('update')->once()->with($existingItem1, ['quantity' => 5]);

  // توقعات العنصر الثاني (Delete)
  $cartItemRepo->shouldReceive('findByCartAndItem')->with(500, 102)->once()->andReturn($existingItem2);
  $cartItemRepo->shouldReceive('delete')->once()->with($existingItem2);

  // توقعات العنصر الثالث (Skip)
  $cartItemRepo->shouldReceive('findByCartAndItem')->with(500, 103)->once()->andReturn(null);

  $cartRepo->shouldReceive('loadItems')->once()->andReturn(collect(['id' => 500]));

  $action = new UpdateCartItemsAction($cartRepo, $cartItemRepo);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  Event::assertDispatched(SystemLogEvent::class);
});

it('throws an exception if cart is not found during update', function () {
  $dto = new UpdateCartItemsDTO(1, 10, [['item_id' => 1, 'quantity' => 1]]);

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  $cartRepo->shouldReceive('findByProjectAndUser')->once()->andReturn(null);

  $action = new UpdateCartItemsAction($cartRepo, $cartItemRepo);

  expect(fn() => $action->execute($dto))
    ->toThrow(\Exception::class, 'Cart not found.');
});
