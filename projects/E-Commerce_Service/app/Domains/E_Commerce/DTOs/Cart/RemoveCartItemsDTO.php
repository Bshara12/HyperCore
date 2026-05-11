<?php

namespace App\Domains\E_Commerce\DTOs\Cart;

use App\Domains\E_Commerce\Requests\RemoveCartItemsRequest;

class RemoveCartItemsDTO
{
    public function __construct(
        public int $project_id,
        public int $user_id,
        public array $item_ids,
    ) {}

    public static function fromRequest(RemoveCartItemsRequest $request): self
    {
        return new self(
            project_id: $request->project_id,
            user_id: $request->attributes->get('auth_user')['id'],
            item_ids: collect($request->items)->pluck('item_id')->toArray(),
        );
    }
}
