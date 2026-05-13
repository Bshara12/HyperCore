<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\ProcessPaymentRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->request = new ProcessPaymentRequest();
});

it('has the correct base validation rules', function () {
  $rules = $this->request->rules();

  expect($rules)->toHaveKeys(['amount', 'currency', 'gateway', 'payment_type'])
    ->and($rules['amount'])->toContain('required', 'numeric', 'min:0.01')
    ->and($rules['currency'])->toContain('required', 'size:3')
    ->and($rules['gateway'])->toContain('required', 'in:stripe,paypal,wallet')
    ->and($rules['payment_type'])->toContain('required', 'in:full,installment');
});

it('requires to_wallet_number only when gateway is wallet', function () {
  // الحالة 1: الـ gateway هي wallet
  $this->request->merge(['gateway' => 'wallet']);
  expect($this->request->rules())->toHaveKey('to_wallet_number');

  // الحالة 2: الـ gateway ليست wallet
  $this->request->merge(['gateway' => 'stripe']);
  expect($this->request->rules())->not->toHaveKey('to_wallet_number');
});

it('applies installment rules only when payment_type is installment', function () {
  // الحالة 1: التقسيط مفعل
  $this->request->merge(['payment_type' => 'installment']);
  $rules = $this->request->rules();

  expect($rules)->toHaveKeys([
    'installment_amount',
    'total_installments',
    'down_payment',
    'interval_days'
  ])->and($rules['installment_amount'])->toContain('required')
    ->and($rules['total_installments'])->toContain('required', 'integer');

  // الحالة 2: الدفع كامل (Full)
  $this->request->merge(['payment_type' => 'full']);
  $rules = $this->request->rules();

  expect($rules)->not->toHaveKeys([
    'installment_amount',
    'total_installments'
  ]);
});

it('returns custom validation messages', function () {
  $messages = $this->request->messages();

  expect($messages)->toBeArray()
    ->and($messages['gateway.in'])->toBe('Supported gateways: stripe, paypal, wallet.')
    ->and($messages['payment_type.in'])->toBe('Payment type must be full or installment.')
    ->and($messages['installment_amount.required'])->toBe('Installment amount is required for installment payments.');
});

it('validates a correct installment payment successfully', function () {
  $data = [
    'amount' => 1000,
    'currency' => 'USD',
    'gateway' => 'paypal',
    'payment_type' => 'installment',
    'installment_amount' => 100,
    'total_installments' => 10
  ];

  $validator = Validator::make($data, $this->request->rules());

  expect($validator->passes())->toBeTrue();
});

it('fails validation with custom message for invalid gateway', function () {
  $data = [
    'amount' => 100,
    'currency' => 'USD',
    'gateway' => 'invalid-gateway',
    'payment_type' => 'full'
  ];

  $validator = Validator::make($data, $this->request->rules(), $this->request->messages());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->first('gateway'))->toBe('Supported gateways: stripe, paypal, wallet.');
});
