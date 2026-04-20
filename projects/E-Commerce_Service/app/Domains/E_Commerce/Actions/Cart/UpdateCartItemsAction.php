<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\DTOs\Cart\UpdateCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;

class UpdateCartItemsAction
{
  public function __construct(
    protected CartRepositoryInterface     $cartRepo,
    protected CartItemRepositoryInterface $cartItemRepo,
  ) {}

  public function execute(UpdateCartItemsDTO $dto): array
  {
    $cart = $this->cartRepo->findByProjectAndUser($dto->project_id, $dto->user_id);

    throw_if(! $cart, \Exception::class, 'Cart not found.');

    foreach ($dto->items as $item) {
      $cartItem = $this->cartItemRepo->findByCartAndItem($cart->id, $item['item_id']);

      if (! $cartItem) {
        continue;
      }

      if ($item['quantity'] <= 0) {
        // الكمية صفر أو أقل → احذف العنصر
        $this->cartItemRepo->delete($cartItem);
      } else {
        // تحديث الكمية فقط — السعر يجي من CMS عند العرض
        $this->cartItemRepo->update($cartItem, [
          'quantity' => $item['quantity'],
        ]);
      }
    }

    return $this->cartRepo->loadItems($cart)->toArray();
  }
}
