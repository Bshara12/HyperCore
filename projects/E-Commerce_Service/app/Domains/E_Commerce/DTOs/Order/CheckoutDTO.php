<?php

namespace App\Domains\E_Commerce\DTOs\Order;

use App\Domains\E_Commerce\Requests\CheckoutRequest;

class CheckoutDTO
{
  public function __construct(
    public int $project_id,
    public int $user_id,
    public string $user_name,
    public int $cart_id,
    public string $payment_method,
    public ?string $gateway,
    public ?string $payment_type,
    public array $address,
  ) {}

  public static function fromRequest(CheckoutRequest $request): self
  {
    return new self(
      project_id: $request->project_id,
      user_id: $request->attributes->get('auth_user')['id'],
      user_name: $request->attributes->get('auth_user')['name'],
      cart_id: $request->cart_id,
      payment_method: $request->payment_method,
      gateway: $request->gateway,
      payment_type: $request->payment_type,
      address: $request->address,
    );
  }
}
