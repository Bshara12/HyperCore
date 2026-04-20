<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

use App\Models\Wishlist;

class WishlistDetailsDTO
{
    public function __construct(
        public int $id,
        public string $name,
        public bool $is_default,
        public string $visibility,
        public bool $is_shareable,
        public ?string $share_token,
        public ?int $user_id,
        public ?string $guest_token,
        public array $items,
        public string $created_at,
        public string $updated_at,
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
            user_id: $wishlist->user_id,
            guest_token: $wishlist->guest_token,
            items: $wishlist->items
                ->map(fn ($item) => WishlistItemDTO::fromModel($item)->toArray())
                ->toArray(),
            created_at: $wishlist->created_at?->toISOString(),
            updated_at: $wishlist->updated_at?->toISOString(),
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
            'user_id' => $this->user_id,
            'guest_token' => $this->guest_token,
            'items' => $this->items,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
