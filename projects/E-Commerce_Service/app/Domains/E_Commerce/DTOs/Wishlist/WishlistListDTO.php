<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

use App\Models\Wishlist;

class WishlistListDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_default,
        public string $visibility,
        public bool $is_shareable,
        public ?string $share_token,
        public int $items_count,
        public string $created_at,
    ) {
    }

    public static function fromModel(Wishlist $wishlist): self
    {
        return new self(
            id: $wishlist->id,
            name: $wishlist->name,
            is_default: (bool) $wishlist->is_default,
            visibility: $wishlist->visibility,
            is_shareable: (bool) $wishlist->is_shareable,
            share_token: $wishlist->share_token,
            items_count: $wishlist->items_count ?? $wishlist->items->count(),
            created_at: $wishlist->created_at?->toISOString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_default' => $this->is_default,
            'visibility' => $this->visibility,
            'is_shareable' => $this->is_shareable,
            'share_token' => $this->share_token,
            'items_count' => $this->items_count,
            'created_at' => $this->created_at,
        ];
    }
}
