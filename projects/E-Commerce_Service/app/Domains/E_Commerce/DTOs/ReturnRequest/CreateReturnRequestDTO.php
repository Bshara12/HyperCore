<?php

namespace App\Domains\E_Commerce\DTOs\ReturnRequest;

class CreateReturnRequestDTO
{
    public function __construct(
        public int $user_id,
        public int $order_id,
        public int $order_item_id,
        public string $description,
        public int $quantity,
        public int $project_id // 🔥 أضفها
    ) {}

    public static function fromRequest($request): self
    {
        return new self(
            user_id: $request->attributes->get('auth_user')['id'],
            order_id: $request->order_id,
            order_item_id: $request->order_item_id,
            description: $request->description,
            quantity: $request->quantity,
            project_id: $request->project_id // 🔥 جاي من middleware
        );
    }
}
