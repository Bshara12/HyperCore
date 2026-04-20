<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $base = [
      'payment_id'     => ['required', 'integer', 'exists:payments,id'],
      // 'gateway'        => ['required', 'string', 'in:stripe,paypal,wallet'],
      'amount'         => ['required', 'numeric', 'min:0.01'],
      // 'currency'       => ['required', 'string', 'size:3'],
      'reason'         => ['nullable', 'string', 'max:500'],
    ];

    // استرداد gateway يحتاج transaction_id

    // if ($this->input('gateway') !== 'wallet') {
    //   $base['transaction_id'] = ['required', 'string'];
    // }

    // استرداد wallet يحتاج from_wallet_id

    // if ($this->input('gateway') === 'wallet') {
    //   $base['to_wallet_number'] = ['required', 'exists:wallets,wallet_number'];
    // }

    return $base;
  }
}
