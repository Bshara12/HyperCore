<?php

namespace App\Domains\E_Commerce\DTOs\Wishlist;

class CreateWishlistDTO
{
    public function __construct(
        public ?int $user_id,
        public ?string $guest_token,
        public string $name,
        public string $visibility = 'private',
        public bool $is_default = false,
        public bool $is_shareable = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            user_id: $data['user_id'] ?? null,
            guest_token: $data['guest_token'] ?? null,
            name: $data['name'],
            visibility: $data['visibility'] ?? 'private',
            is_default: (bool) ($data['is_default'] ?? false),
            is_shareable: (bool) ($data['is_shareable'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'user_id' => $this->user_id,
            'guest_token' => $this->guest_token,
            'name' => $this->name,
            'visibility' => $this->visibility,
            'is_default' => $this->is_default,
            'is_shareable' => $this->is_shareable,
        ];
    }
}
