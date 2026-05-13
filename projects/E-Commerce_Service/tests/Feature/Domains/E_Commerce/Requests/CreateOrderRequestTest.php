<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CreateOrderRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CreateOrderRequest())->rules();
});

it('validates order address with coordinates', function ($address, $shouldPass) {
  // تخطي الـ exists للتمكن من الاختبار بدون قاعدة بيانات
  $rules = $this->rules;
  $rules['cart_id'] = ['required', 'integer'];

  $data = [
    'cart_id' => 1,
    'address' => $address
  ];

  $validator = Validator::make($data, $rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'full address with coordinates' => [[
    'full_address' => '123 Nile St',
    'city' => 'Giza',
    'street' => 'Pyramids Road',
    'latitude' => 30.0131,
    'longitude' => 31.2089,
    'phone' => '01000000000'
  ], true],
  'address without coordinates' => [[
    'full_address' => '123 Nile St',
    'city' => 'Giza',
    'street' => 'Pyramids Road',
    'phone' => '01000000000'
    // latitude & longitude are nullable
  ], true],
  'missing phone number' => [[
    'full_address' => '123 Nile St',
    'city' => 'Giza',
    'street' => 'Pyramids Road',
  ], false],
  'invalid coordinate format' => [[
    'full_address' => '123 Nile St',
    'city' => 'Giza',
    'street' => 'Pyramids Road',
    'latitude' => 'not-a-number',
    'phone' => '01000000000'
  ], false],
]);
