<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetSlotsRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'date' => ['required', 'date', 'date_format:Y-m-d'],
    ];
  }

  public function messages(): array
  {
    return [
      'date.required'    => 'Date is required.',
      'date.date_format' => 'Date must be in Y-m-d format. Example: 2025-01-15',
    ];
  }
}
