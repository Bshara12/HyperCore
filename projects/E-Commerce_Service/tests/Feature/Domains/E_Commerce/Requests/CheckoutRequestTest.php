<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CheckoutRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CheckoutRequest())->rules();
});

it('validates conditional payment fields', function ($paymentMethod, $gateway, $paymentType, $shouldPass) {
  $rules = $this->rules;
  $rules['cart_id'] = ['required', 'integer']; // إلغاء exists للتمكن من الاختبار بدون DB

  $data = [
    'cart_id' => 1,
    'payment_method' => $paymentMethod,
    'address' => [
      'full_address' => '123 Main St',
      'city' => 'Cairo',
      'street' => 'Tahrir',
      'phone' => '0123456789'
    ]
  ];

  // إضافة الحقول الشرطية فقط إذا كانت موجودة
  if ($gateway) $data['gateway'] = $gateway;
  if ($paymentType) $data['payment_type'] = $paymentType;

  $validator = Validator::make($data, $rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'online success'          => ['online', 'stripe', 'full', true],
  'online missing gateway'  => ['online', null, 'full', false],
  'online missing type'     => ['online', 'stripe', null, false],
  'cod success'             => ['cod', null, null, true],
]);

it('validates nested address fields', function ($addressData, $shouldPass) {
  $rules = $this->rules;
  $rules['cart_id'] = ['required', 'integer'];

  $data = [
    'cart_id' => 1,
    'payment_method' => 'cod',
    'address' => $addressData
  ];

  $validator = Validator::make($data, $rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'complete address' => [[
    'full_address' => 'A',
    'city' => 'B',
    'street' => 'C',
    'phone' => '123'
  ], true],
  'missing phone'    => [[
    'full_address' => 'A',
    'city' => 'B',
    'street' => 'C'
  ], false],
]);
