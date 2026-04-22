<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetResourceBookingsRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'status' => ['nullable', 'string', 'in:pending,confirmed,cancelled,completed,no_show'],
      'from'   => ['nullable', 'date', 'date_format:Y-m-d'],
      'to'     => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
    ];
  }
}
