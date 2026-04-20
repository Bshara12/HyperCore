<?php

namespace App\Domains\E_Commerce\DTOs\Order;

use App\Domains\E_Commerce\Requests\CreateOrderRequest;

class CreateOrderDTO
{
  public function __construct(
    public int $project_id,
    public int $user_id,
    public int $cart_id,
    public array $address,
  ) {}

  public static function fromRequest(CreateOrderRequest $request): self
  {
    return new self(
      project_id: $request->project_id,
      user_id: $request->attributes->get('auth_user')['id'],
      cart_id: $request->cart_id,
      address: $request->address,
    );
  }
}
