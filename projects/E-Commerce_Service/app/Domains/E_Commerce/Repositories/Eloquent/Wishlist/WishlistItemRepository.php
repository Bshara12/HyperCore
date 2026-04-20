<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Wishlist;

use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistItemRepositoryInterface;
use App\Models\WishlistItem;
use Illuminate\Database\Eloquent\Collection;

class WishlistItemRepository implements WishlistItemRepositoryInterface
{
    public function create(array $data): WishlistItem
    {
        return WishlistItem::create($data);
    }

    public function update(WishlistItem $item, array $data): bool
    {
        return $item->update($data);
    }

    public function delete(WishlistItem $item): bool
    {
        return (bool) $item->delete();
    }

    public function findById(int $itemId): ?WishlistItem
    {
        return WishlistItem::query()->find($itemId);
    }

    public function findByIdInWishlist(int $itemId, int $wishlistId): ?WishlistItem
    {
        return WishlistItem::query()
            ->where('id', $itemId)
            ->where('wishlist_id', $wishlistId)
            ->first();
    }

    public function getByWishlistId(int $wishlistId): Collection
    {
        return WishlistItem::query()
            ->where('wishlist_id', $wishlistId)
            ->ordered()
            ->get();
    }

    public function existsInWishlist(
        int $wishlistId,
        int $productId,
        ?int $variantId = null
    ): bool {
        return WishlistItem::query()
            ->where('wishlist_id', $wishlistId)
            ->where('product_id', $productId)
            ->where('variant_id', $variantId)
            ->exists();
    }

    public function getHighestSortOrder(int $wishlistId): int
    {
        return (int) WishlistItem::query()
            ->where('wishlist_id', $wishlistId)
            ->max('sort_order');
    }

    public function countByWishlistId(int $wishlistId): int
    {
        return WishlistItem::query()
            ->where('wishlist_id', $wishlistId)
            ->count();
    }
}
