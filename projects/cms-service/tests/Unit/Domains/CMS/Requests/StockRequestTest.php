<?php

use App\Domains\CMS\Requests\StockRequest;
use Illuminate\Support\Facades\Validator;

test('it passes validation with valid data', function () {
  $data = [
    'items' => [
      ['product_id' => 1, 'quantity' => 10],
      ['product_id' => 2, 'quantity' => 1],
    ]
  ];

  $request = new StockRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->passes())->toBeTrue();
});

test('it fails validation with invalid data', function ($data) {
  $request = new StockRequest();
  $validator = Validator::make($data, $request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'missing items' => [[]],
  'product_id not integer' => [['items' => [['product_id' => 'abc', 'quantity' => 1]]]],
  'quantity is zero' => [['items' => [['product_id' => 1, 'quantity' => 0]]]],
  'quantity is negative' => [['items' => [['product_id' => 1, 'quantity' => -5]]]],
  'missing quantity' => [['items' => [['product_id' => 1]]]],
]);

test('it returns items via helper method', function () {
  $request = new StockRequest();
  $inputItems = [
    ['product_id' => 1, 'quantity' => 5]
  ];

  // نقوم بدمج البيانات في الطلب لمحاكاة وصولها من المستخدم
  $request->merge(['items' => $inputItems]);

  expect($request->items())->toBe($inputItems);
});
