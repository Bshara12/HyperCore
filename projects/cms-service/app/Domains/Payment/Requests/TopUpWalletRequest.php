<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TopUpWalletRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'wallet_number' => ['required'],
      'amount'  => ['required', 'numeric', 'min:0.01'],
      'note'    => ['nullable', 'string', 'max:255'],
    ];
  }
}
