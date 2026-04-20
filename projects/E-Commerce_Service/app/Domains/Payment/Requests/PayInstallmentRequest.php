<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentRequest extends FormRequest
{
  public function rules(): array
  {
    $base = [
      'payment_id' => ['required', 'integer'],
      'gateway'    => ['required', 'string', 'in:stripe,paypal,wallet'],
      'currency'   => ['required', 'string', 'size:3'],
    ];

    if ($this->input('gateway') === 'wallet') {
      $base['to_wallet_number'] = ['required'];
    }

    return $base;
  }
}
