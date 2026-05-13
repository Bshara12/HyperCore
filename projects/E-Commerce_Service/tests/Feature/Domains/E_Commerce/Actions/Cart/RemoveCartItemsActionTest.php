<?php

use App\Domains\E_Commerce\Actions\Cart\RemoveCartItemsAction;
use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Models\Cart;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

it('removes specific items from cart and clears cache', function () {
  Event::fake();
  Cache::shouldReceive('forget')->once();

  $projectId = 1;
  $userId = 10;
  $itemIds = [101, 102];

  $dto = new RemoveCartItemsDTO(
    project_id: $projectId,
    user_id: $userId,
    item_ids: $itemIds
  );

  // 1. إعداد موديبل حقيقي لتجنب TypeError
  $mockCart = new Cart(['id' => 500]);

  // 2. بناء الـ Mocks
  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  $cartRepo->shouldReceive('findByProjectAndUser')
    ->once()
    ->with($projectId, $userId)
    ->andReturn($mockCart);

  $cartItemRepo->shouldReceive('deleteByIds')
    ->once()
    ->with($mockCart->id, $itemIds);

  $cartRepo->shouldReceive('loadItems')
    ->once()
    ->with($mockCart)
    ->andReturn(collect(['id' => 500, 'items' => []]));

  $action = new RemoveCartItemsAction($cartRepo, $cartItemRepo);

  // 3. التنفيذ
  $result = $action->execute($dto);

  // 4. التحقق
  expect($result)->toBeArray();
  Event::assertDispatched(SystemLogEvent::class, function ($event) use ($userId, $mockCart) {
    return $event->eventType === 'remove_cart_item' &&
      $event->userId === $userId &&
      $event->entityId === $mockCart->id;
  });
});

it('throws an exception if cart does not exist during removal', function () {
  $dto = new RemoveCartItemsDTO(1, 10, [101]);

  $cartRepo = Mockery::mock(CartRepositoryInterface::class);
  $cartItemRepo = Mockery::mock(CartItemRepositoryInterface::class);

  $cartRepo->shouldReceive('findByProjectAndUser')
    ->once()
    ->andReturn(null);

  $action = new RemoveCartItemsAction($cartRepo, $cartItemRepo);

  // التحقق من تغطية سطر throw_if
  expect(fn() => $action->execute($dto))
    ->toThrow(\Exception::class, 'Cart not found.');
});
