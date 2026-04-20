<?php

namespace App\Domains\E_Commerce\Repositories\Interfaces\Wishlist;

use App\Models\Wishlist;
use Illuminate\Database\Eloquent\Collection;

interface WishlistRepositoryInterface
{
    public function create(array $data): Wishlist;

    public function update(Wishlist $wishlist, array $data): bool;

    public function delete(Wishlist $wishlist): bool;

    public function findById(int $wishlistId): ?Wishlist;

    public function findByIdForUser(int $wishlistId, int $userId): ?Wishlist;

    public function findByIdForGuest(int $wishlistId, string $guestToken): ?Wishlist;

    public function getByUserId(int $userId): Collection;

    public function getByGuestToken(string $guestToken): Collection;

    public function getDefaultByUserId(int $userId): ?Wishlist;

    public function getDefaultByGuestToken(string $guestToken): ?Wishlist;

    public function findByShareToken(string $shareToken): ?Wishlist;

    public function existsByName(int $userId, string $name): bool;

    public function findByIdWithItems(int $wishlistId): ?Wishlist;

    public function findByIdWithItemsForUser(
        int $wishlistId,
        int $userId
    ): ?Wishlist;

    public function findByShareTokenWithItems(string $shareToken): ?Wishlist;
}
