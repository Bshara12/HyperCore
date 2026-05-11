<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Cart;

use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartItemRepositoryInterface;
use App\Models\CartItem;

class EloquentCartItemRepository implements CartItemRepositoryInterface
{
    public function findByCartAndItem(int $cart_id, int $item_id)
    {
        return CartItem::where('cart_id', $cart_id)
            ->where('item_id', $item_id)
            ->first();
    }

    public function create(array $data)
    {
        return CartItem::create($data);
    }

    public function update(CartItem $cartItem, array $data)
    {
        $cartItem->update($data);

        return $cartItem;
    }

    public function delete(CartItem $cartItem)
    {
        $cartItem->delete();
    }

    public function deleteByIds(int $cart_id, array $item_ids)
    {
        CartItem::where('cart_id', $cart_id)
            ->whereIn('item_id', $item_ids)
            ->delete();
    }

    public function deleteByCartId(int $cart_id)
    {
        return CartItem::where('cart_id', $cart_id)->delete();
    }
}
