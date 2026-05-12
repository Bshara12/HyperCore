<?php

namespace App\Domains\E_Commerce\Repositories\Eloquent\Wishlist;

use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistRepositoryInterface;
use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;

class WishlistRepository implements WishlistRepositoryInterface
{
    public function create(array $data): Wishlist
    {
        return Wishlist::create($data);
    }

    public function update(Wishlist $wishlist, array $data): bool
    {
        return $wishlist->update($data);
    }

    public function delete(Wishlist $wishlist): bool
    {
        return (bool) $wishlist->delete();
    }

    public function findById(int $wishlistId): ?Wishlist
    {
        return Wishlist::query()->find($wishlistId);
    }

    public function findByIdForUser(int $wishlistId, int $userId): ?Wishlist
    {
        return Wishlist::query()
            ->forUser($userId)
            ->where('id', $wishlistId)
            ->first();
    }

    public function findByIdForGuest(int $wishlistId, string $guestToken): ?Wishlist
    {
        return Wishlist::query()
            ->forGuest($guestToken)
            ->where('id', $wishlistId)
            ->first();
    }

    public function getByUserId(int $userId): Collection
    {
        return Wishlist::query()
            ->forUser($userId)
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getByGuestToken(string $guestToken): Collection
    {
        return Wishlist::query()
            ->forGuest($guestToken)
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function getDefaultByUserId(int $userId): ?Wishlist
    {
        return Wishlist::query()
            ->forUser($userId)
            ->where('is_default', true)
            ->first();
    }

    public function getDefaultByGuestToken(string $guestToken): ?Wishlist
    {
        return Wishlist::query()
            ->forGuest($guestToken)
            ->where('is_default', true)
            ->first();
    }

    public function findByShareToken(string $shareToken): ?Wishlist
    {
        return Wishlist::query()
            ->public()
            ->where('is_shareable', true)
            ->where('share_token', $shareToken)
            ->first();
    }

    public function existsByName(int $userId, string $name): bool
    {
        return Wishlist::query()
            ->forUser($userId)
            ->where('name', $name)
            ->exists();
    }

    public function findByIdWithItems(int $wishlistId): ?Wishlist
    {
        return Wishlist::query()
            ->with(['items'])
            ->find($wishlistId);
    }

    public function findByIdWithItemsForUser(
        int $wishlistId,
        int $userId
    ): ?Wishlist {
        return Wishlist::query()
            ->forUser($userId)
            ->with(['items'])
            ->where('id', $wishlistId)
            ->first();
    }

    public function findByShareTokenWithItems(string $shareToken): ?Wishlist
    {
        return Wishlist::query()
            ->public()
            ->where('is_shareable', true)
            ->where('share_token', $shareToken)
            ->with(['items'])
            ->first();
    }
}
