<?php

namespace App\Domains\E_Commerce\DTOs\Cart;

use App\Domains\E_Commerce\Requests\CreateCartRequest;

class AddCartItemsDTO
{
  public function __construct(
    public int $project_id,
    public int $user_id,
    public array $items,
  ) {}

  public static function fromRequest(CreateCartRequest $request): self
  {
    return new self(
      project_id: $request->project_id,
      user_id: $request->attributes->get('auth_user')['id'],
      items: $request->items
    );
  }
}
