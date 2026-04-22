<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResourceRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'name'          => ['sometimes', 'string', 'max:255'],
      'type'          => ['sometimes', 'string', 'max:100'],
      'capacity'      => ['sometimes', 'integer', 'min:1'],
      'status'        => ['sometimes', 'string', 'in:active,inactive'],
      'payment_type'  => ['sometimes', 'string', 'in:free,paid'],
      'price'         => ['sometimes', 'nullable', 'numeric', 'min:0.01'],
      'settings'      => ['sometimes', 'array'],
    ];
  }
}
