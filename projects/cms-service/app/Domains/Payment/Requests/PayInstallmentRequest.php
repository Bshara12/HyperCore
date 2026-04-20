<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayInstallmentRequest extends FormRequest
{
  public function rules(): array
  {
    $base = [
      'payment_id' => ['exists:payments,id']
    ];

    if ($this->input('gateway') === 'wallet') {
      $base['to_wallet_number'] = ['exists:wallets,wallet_number'];
    }

    return $base;
  }
}
