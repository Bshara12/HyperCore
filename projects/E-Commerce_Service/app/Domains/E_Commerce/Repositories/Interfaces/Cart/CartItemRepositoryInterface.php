<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Cart;

use App\Models\CartItem;

interface CartItemRepositoryInterface
{
    public function findByCartAndItem(int $cartId, int $itemId);

    public function create(array $data);

    public function update(CartItem $cartItem, array $data);

    public function deleteByIds(int $cart_id, array $item_ids);

    public function delete(CartItem $cartItem);

    public function deleteByCartId(int $cartId);
}
