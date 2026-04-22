<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateResourceRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'data_entry_id' => ['required', 'integer'],
      'name'          => ['required', 'string', 'max:255'],
      'type'          => ['required', 'string', 'max:100'],
      'capacity'      => ['nullable', 'integer', 'min:1'],
      'payment_type'  => ['required', 'string', 'in:free,paid'],
      'price'         => [
        'nullable',
        'numeric',
        'min:0.01',
        $this->input('payment_type') === 'paid' ? 'required' : 'nullable',
      ],
      'settings'      => ['nullable', 'array'],
    ];
  }

  public function messages(): array
  {
    return [
      'price.required' => 'Price is required when payment type is paid.',
      'price.min'      => 'Price must be greater than zero.',
    ];
  }
}
