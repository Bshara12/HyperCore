<?php

namespace App\Domains\Booking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetCancellationPolicyRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'policies'                       => ['required', 'array', 'min:1'],
      'policies.*.hours_before'        => ['required', 'integer', 'min:0'],
      'policies.*.refund_percentage'   => ['required', 'integer', 'between:0,100'],
      'policies.*.description'         => ['nullable', 'string', 'max:255'],
    ];
  }
}
