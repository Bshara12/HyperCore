<?php

namespace App\Domains\E_Commerce\Services;

use App\Domains\E_Commerce\Repositories\Interfaces\Wishlist\WishlistRepositoryInterface;
use App\Models\Wishlist;
use DomainException;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class WishlistService
{
    public function __construct(
        protected WishlistRepositoryInterface $wishlistRepository,
    ) {
    }

    protected function validateWishlistOwnership(
        ?int $userId,
        ?string $guestToken
    ): void {
        if ($userId && $guestToken) {
            throw new DomainException(
                'Wishlist cannot belong to both user and guest.'
            );
        }

        if (! $userId && ! $guestToken) {
            throw new DomainException(
                'Wishlist must belong to either user or guest.'
            );
        }
    }

    public function createForUser(int $userId, array $data): Wishlist
    {
        $this->validateWishlistOwnership($userId, null);

        $isFirstWishlist = $this->wishlistRepository
            ->getByUserId($userId)
            ->isEmpty();

        $wishlistData = [
            'user_id' => $userId,
            'name' => $data['name'],
            'visibility' => $data['visibility'] ?? 'private',
            'is_default' => $isFirstWishlist,
            'is_shareable' => false,
            'share_token' => null,
        ];

        return $this->wishlistRepository->create($wishlistData);
    }

    public function createForGuest(string $guestToken, array $data): Wishlist
    {
        $this->validateWishlistOwnership(null, $guestToken);

        $isFirstWishlist = $this->wishlistRepository
            ->getByGuestToken($guestToken)
            ->isEmpty();

        $wishlistData = [
            'guest_token' => $guestToken,
            'name' => $data['name'],
            'visibility' => $data['visibility'] ?? 'private',
            'is_default' => $isFirstWishlist,
            'is_shareable' => false,
            'share_token' => null,
        ];

        return $this->wishlistRepository->create($wishlistData);
    }

    public function getUserWishlists(int $userId): Collection
    {
        return $this->wishlistRepository->getByUserId($userId);
    }

    public function getGuestWishlists(string $guestToken): Collection
    {
        return $this->wishlistRepository->getByGuestToken($guestToken);
    }

    public function getUserWishlistOrFail(int $wishlistId, int $userId): Wishlist
    {
        $wishlist = $this->wishlistRepository
            ->findByIdForUser($wishlistId, $userId);

        abort_if(! $wishlist, 404, 'Wishlist not found.');

        return $wishlist;
    }

    public function getGuestWishlistOrFail(
        int $wishlistId,
        string $guestToken
    ): Wishlist {
        $wishlist = $this->wishlistRepository
            ->findByIdForGuest($wishlistId, $guestToken);

        abort_if(! $wishlist, 404, 'Wishlist not found.');

        return $wishlist;
    }

    public function update(Wishlist $wishlist, array $data): Wishlist
    {
        return DB::transaction(function () use ($wishlist, $data) {

            if (isset($data['is_default']) && $data['is_default']) {
                $this->unsetCurrentDefaultWishlist($wishlist);
            }

            if (
                isset($data['visibility']) &&
                $data['visibility'] === 'public' &&
                empty($wishlist->share_token)
            ) {
                $data['share_token'] = Str::uuid()->toString();
                $data['is_shareable'] = true;
            }

            $this->wishlistRepository->update($wishlist, $data);

            return $wishlist->refresh();
        });
    }

    public function delete(Wishlist $wishlist): bool
    {
        return $this->wishlistRepository->delete($wishlist);
    }

    public function generateShareToken(Wishlist $wishlist): Wishlist
    {
        $this->wishlistRepository->update($wishlist, [
            'visibility' => 'public',
            'is_shareable' => true,
            'share_token' => Str::uuid()->toString(),
        ]);

        return $wishlist->refresh();
    }

    public function getPublicWishlist(string $shareToken): Wishlist
    {
        $wishlist = $this->wishlistRepository
            ->findByShareToken($shareToken);

        abort_if(! $wishlist, 404, 'Wishlist not found.');

        return $wishlist;
    }

    protected function unsetCurrentDefaultWishlist(Wishlist $wishlist): void
    {
        if ($wishlist->user_id) {
            $currentDefault = $this->wishlistRepository
                ->getDefaultByUserId($wishlist->user_id);

            if ($currentDefault) {
                $this->wishlistRepository->update($currentDefault, [
                    'is_default' => false,
                ]);
            }
        }

        if ($wishlist->guest_token) {
            $currentDefault = $this->wishlistRepository
                ->getDefaultByGuestToken($wishlist->guest_token);

            if ($currentDefault) {
                $this->wishlistRepository->update($currentDefault, [
                    'is_default' => false,
                ]);
            }
        }
    }
}
