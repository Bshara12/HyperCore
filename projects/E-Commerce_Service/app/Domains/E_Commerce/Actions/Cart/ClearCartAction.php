<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\DTOs\Cart\ClearCartDTO;
use App\Domains\E_Commerce\Repositories\CartRepository;
use App\Domains\E_Commerce\Repositories\CartItemRepository;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Events\SystemLogEvent;
use RuntimeException;

class ClearCartAction
{
  public function __construct(
    protected CartRepositoryInterface $cartRepo,
    protected CartItemRepositoryInterface $cartItemRepo
  ) {}

  public function execute($project_id, $user_id)
  {
    $cart = $this->cartRepo->findByProjectAndUser($project_id, $user_id);

    if (! $cart) {
      throw new RuntimeException('Cart not found');
    }

    $this->cartItemRepo->deleteByCartId($cart->id);


    event(new SystemLogEvent(
      module: 'ecommerce',
      eventType: 'clear_cart',
      userId: $user_id,
      entityType: 'cart',
      entityId: $cart->id
    ));

    return $this->cartRepo->loadItems($cart);
  }
}
