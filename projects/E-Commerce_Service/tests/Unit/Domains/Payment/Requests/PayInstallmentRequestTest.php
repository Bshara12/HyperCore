<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\PayInstallmentRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->request = new PayInstallmentRequest();
});

it('has the correct base validation rules', function () {
  $rules = $this->request->rules();

  expect($rules)->toHaveKeys(['payment_id', 'gateway', 'currency'])
    ->and($rules['payment_id'])->toContain('required', 'integer')
    ->and($rules['gateway'])->toContain('in:stripe,paypal,wallet')
    ->and($rules['currency'])->toContain('size:3');
});

it('requires to_wallet_number when gateway is wallet', function () {
  // محاكاة إدخال gateway = wallet
  $this->request->merge(['gateway' => 'wallet']);

  $rules = $this->request->rules();

  expect($rules)->toHaveKey('to_wallet_number')
    ->and($rules['to_wallet_number'])->toContain('required');
});

it('does not require to_wallet_number when gateway is stripe', function () {
  // محاكاة إدخال gateway = stripe
  $this->request->merge(['gateway' => 'stripe']);

  $rules = $this->request->rules();

  expect($rules)->not->toHaveKey('to_wallet_number');
});

it('validates data against the rules correctly', function () {
  $data = [
    'payment_id' => 123,
    'gateway' => 'paypal',
    'currency' => 'USD'
  ];

  $validator = Validator::make($data, $this->request->rules());

  expect($validator->passes())->toBeTrue();
});

it('fails validation if currency size is not 3', function () {
  $data = [
    'payment_id' => 123,
    'gateway' => 'paypal',
    'currency' => 'US' // خطأ: الحجم يجب أن يكون 3
  ];

  $validator = Validator::make($data, $this->request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('currency'))->toBeTrue();
});
