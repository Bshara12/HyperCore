<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
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
    
    Cache::forget(CacheKeys::cart($user_id, $project_id));

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
