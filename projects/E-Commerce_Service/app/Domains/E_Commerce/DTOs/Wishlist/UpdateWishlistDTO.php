<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

class UpdateWishlistDTO
{
    public function __construct(
        public ?string $name = null,
        public ?string $visibility = null,
        public ?bool $is_default = null,
        public ?bool $is_shareable = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? null,
            visibility: $data['visibility'] ?? null,
            is_default: isset($data['is_default'])
                ? (bool) $data['is_default']
                : null,
            is_shareable: isset($data['is_shareable'])
                ? (bool) $data['is_shareable']
                : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'visibility' => $this->visibility,
            'is_default' => $this->is_default,
            'is_shareable' => $this->is_shareable,
        ], fn ($value) => ! is_null($value));
    }
}
