<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

class AddWishlistItemDTO
{
    public function __construct(
        public int $wishlist_id,
        public int $product_id,
        public ?int $variant_id = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            wishlist_id: (int) $data['wishlist_id'],
            product_id: (int) $data['product_id'],
            variant_id: isset($data['variant_id'])
                ? (int) $data['variant_id']
                : null,
        );
    }

    public function toArray(): array
    {
        return [
            'wishlist_id' => $this->wishlist_id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
        ];
    }
}
