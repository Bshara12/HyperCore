<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

use App\Models\WishlistItem;

class WishlistItemDTO
{
    public function __construct(
        public int $id,
        public int $product_id,
        public ?int $variant_id,
        public int $sort_order,
        public ?array $product_snapshot,
        public string $created_at,
        public string $updated_at,
    ) {
    }

    public static function fromModel(WishlistItem $item): self
    {
        return new self(
            id: $item->id,
            product_id: $item->product_id,
            variant_id: $item->variant_id,
            sort_order: $item->sort_order,
            product_snapshot: $item->product_snapshot,
            created_at: $item->created_at?->toISOString(),
            updated_at: $item->updated_at?->toISOString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'sort_order' => $this->sort_order,
            'product_snapshot' => $this->product_snapshot,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
