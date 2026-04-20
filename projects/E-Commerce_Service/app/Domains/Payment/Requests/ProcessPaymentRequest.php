<?php

namespace App\Domains\Payment\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
{
  public function rules(): array
  {
    $base = [
      'amount'       => ['required', 'numeric', 'min:0.01'],
      'currency'     => ['required', 'string', 'size:3'],
      'gateway'      => ['required', 'string', 'in:stripe,paypal,wallet'],
      'payment_type' => ['required', 'string', 'in:full,installment'],
      'description'  => ['nullable', 'string', 'max:500'],
    ];

    if ($this->input('gateway') === 'wallet') {
      $base['to_wallet_number'] = ['required'];
    }

    // حقول التقسيط
    if ($this->input('payment_type') === 'installment') {
      $base['down_payment']       = ['nullable', 'numeric', 'min:0'];
      $base['installment_amount'] = ['required', 'numeric', 'min:0.01'];
      $base['total_installments'] = ['required', 'integer', 'min:1'];
      $base['interval_days']      = ['nullable', 'integer', 'min:1'];
    }

    return $base;
  }

  public function messages(): array
  {
    return [
      'gateway.in'                  => 'Supported gateways: stripe, paypal, wallet.',
      'payment_type.in'             => 'Payment type must be full or installment.',
      'installment_amount.required' => 'Installment amount is required for installment payments.',
      'total_installments.required' => 'Total installments count is required.',
    ];
  }
}
