<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\DTOs\Cart\AddCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Events\SystemLogEvent;

class AddCartItemAction
{
  public function __construct(
    protected CartRepositoryInterface     $cartRepo,
    protected CartItemRepositoryInterface $cartItemRepo,
  ) {}

  public function execute(AddCartItemsDTO $dto)
  {
    $cart = $this->cartRepo->getOrCreate($dto->project_id, $dto->user_id);

    foreach ($dto->items as $item) {
      $item_id  = $item['item_id'];
      $quantity = $item['quantity'];

      $cartItem = $this->cartItemRepo->findByCartAndItem($cart->id, $item_id);

      if ($cartItem) {
        // العنصر موجود → نجمع الكميات
        $this->cartItemRepo->update($cartItem, [
          'quantity' => $cartItem->quantity + $quantity,
        ]);
      } else {
        // عنصر جديد → نضيفه بدون سعر
        $this->cartItemRepo->create([
          'cart_id'  => $cart->id,
          'item_id'  => $item_id,
          'quantity' => $quantity,
        ]);
      }
    }

    event(new SystemLogEvent(
      module: 'ecommerce',
      eventType: 'create_cart_item',
      userId: $dto->user_id,
      entityType: 'cart',
      entityId: $cart->id
    ));
    return $this->cartRepo->loadItems($cart);
  }
}
