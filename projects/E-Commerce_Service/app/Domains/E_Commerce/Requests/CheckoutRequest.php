<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'cart_id' => ['required', 'integer', 'exists:carts,id'],

      'payment_method' => ['required', 'in:online,cod'],

      // فقط إذا online
      'gateway' => ['required_if:payment_method,online', 'in:stripe,paypal,wallet'],
      'payment_type' => ['required_if:payment_method,online', 'in:full,installment'],

      // address
      'address' => ['required', 'array'],
      'address.full_address' => ['required', 'string'],
      'address.city' => ['required', 'string'],
      'address.street' => ['required', 'string'],
      'address.phone' => ['required'],
    ];
  }
}
