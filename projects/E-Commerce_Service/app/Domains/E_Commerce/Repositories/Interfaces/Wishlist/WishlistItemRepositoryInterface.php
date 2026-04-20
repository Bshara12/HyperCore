<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Wishlist;

use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Collection;

interface WishlistItemRepositoryInterface
{
    public function create(array $data): WishlistItem;

    public function update(WishlistItem $item, array $data): bool;

    public function delete(WishlistItem $item): bool;

    public function findById(int $itemId): ?WishlistItem;

    public function findByIdInWishlist(int $itemId, int $wishlistId): ?WishlistItem;

    public function getByWishlistId(int $wishlistId): Collection;

    public function existsInWishlist(
        int $wishlistId,
        int $productId,
        ?int $variantId = null
    ): bool;

    public function getHighestSortOrder(int $wishlistId): int;

    public function countByWishlistId(int $wishlistId): int;
}
