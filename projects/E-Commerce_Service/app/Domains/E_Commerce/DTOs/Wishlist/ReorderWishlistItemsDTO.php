<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

class ReorderWishlistItemsDTO
{
    public function __construct(
        public int $wishlist_id,
        public array $items,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            wishlist_id: (int) $data['wishlist_id'],
            items: $data['items'],
        );
    }

    public function toArray(): array
    {
        return [
            'wishlist_id' => $this->wishlist_id,
            'items' => $this->items,
        ];
    }
}
