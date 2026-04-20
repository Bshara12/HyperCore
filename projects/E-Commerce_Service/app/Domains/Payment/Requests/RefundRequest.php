<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'payment_id'     => ['required', 'integer', 'exists:payments,id'],
      'transaction_id' => ['required', 'string'],
      'amount'         => ['required', 'numeric', 'min:0.01'],
      'currency'       => ['required', 'string', 'size:3'],
      'gateway'        => ['required', 'string', 'in:stripe,paypal'],
      'reason'         => ['nullable', 'string', 'max:500'],
    ];
  }
}
