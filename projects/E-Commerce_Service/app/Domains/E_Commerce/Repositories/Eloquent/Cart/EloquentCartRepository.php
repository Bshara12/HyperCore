<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Cart;

use App\Domains\E_Commerce\Repositories\Interfaces\Cart\CartRepositoryInterface;
use App\Models\Cart;

class EloquentCartRepository implements CartRepositoryInterface
{
    public function getOrCreate(int $project_id, int $user_id): Cart
    {
        return Cart::firstOrCreate([
            'project_id' => $project_id,
            'user_id' => $user_id,
        ]);
    }

    public function findByProjectAndUser(int $project_id, int $user_id): ?Cart
    {
        return Cart::where('project_id', $project_id)
            ->where('user_id', $user_id)
            ->first();
    }

    public function loadItems(Cart $cart): Cart
    {
        return $cart->load('items');
    }

    public function findById(int $id)
    {
        return Cart::find($id);
    }

    public function delete(int $id): void
    {
        Cart::where('id', $id)->delete();
    }
}
