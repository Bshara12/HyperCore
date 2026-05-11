<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

class MoveWishlistItemToCartDTO
{
    public function __construct(
        public int $wishlist_id,
        public int $wishlist_item_id,
        public int $project_id,
        public int $user_id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            wishlist_id: (int) $data['wishlist_id'],
            wishlist_item_id: (int) $data['wishlist_item_id'],
            project_id: (int) $data['project_id'],
            user_id: (int) $data['user_id'],
        );
    }

    public function toArray(): array
    {
        return [
            'wishlist_id' => $this->wishlist_id,
            'wishlist_item_id' => $this->wishlist_item_id,
            'project_id' => $this->project_id,
            'user_id' => $this->user_id,
        ];
    }
}
