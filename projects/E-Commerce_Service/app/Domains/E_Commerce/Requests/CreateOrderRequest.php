<?php

namespace App\Domains\E_Commerce\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'cart_id' => ['required', 'integer', 'exists:carts,id'],

      // ✅ الجديد
      'address' => ['required', 'array'],
      'address.full_address' => ['required', 'string'],
      'address.city' => ['required', 'string'],
      'address.street' => ['required', 'string'],
      'address.latitude' => ['nullable', 'numeric'],
      'address.longitude' => ['nullable', 'numeric'],
      'address.phone' => ['required', 'string'],
    ];
  }
}
