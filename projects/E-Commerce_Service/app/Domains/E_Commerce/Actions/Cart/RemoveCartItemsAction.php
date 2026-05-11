<?php

namespace App\Domains\E_Commerce\Actions\Cart;

use App\Domains\E_Commerce\DTOs\Cart\RemoveCartItemsDTO;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Domains\E_Commerce\Support\CacheKeys;
use App\Events\SystemLogEvent;
use Illuminate\Support\Facades\Cache;

class RemoveCartItemsAction
{
    public function __construct(
        protected CartRepositoryInterface $cartRepo,
        protected CartItemRepositoryInterface $cartItemRepo,
    ) {}

    public function execute(RemoveCartItemsDTO $dto): array
    {
        $cart = $this->cartRepo->findByProjectAndUser($dto->project_id, $dto->user_id);

        throw_if(! $cart, \Exception::class, 'Cart not found.');

        $this->cartItemRepo->deleteByIds($cart->id, $dto->item_ids);

        Cache::forget(CacheKeys::cart($dto->user_id, $dto->project_id));

        event(new SystemLogEvent(
            module: 'ecommerce',
            eventType: 'remove_cart_item',
            userId: $dto->user_id,
            entityType: 'cart',
            entityId: $cart->id
        ));

        return $this->cartRepo->loadItems($cart)->toArray();
    }
}
