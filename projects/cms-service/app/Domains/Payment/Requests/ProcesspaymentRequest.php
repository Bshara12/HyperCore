<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
  public function rules(): array
  {
    if ($this->input('gateway') === 'wallet') {
      $base['toWallet'] = ['exists:wallets,wallet_number'];
    }
    return $base;
  }
}
