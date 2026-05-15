<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CreateReturnRequestRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CreateReturnRequestRequest())->rules();
});

it('validates return request fields correctly', function ($data, $shouldPass) {
  // عزل قواعد الـ exists للاختبار بدون الحاجة لقاعدة بيانات
  $rules = $this->rules;
  $rules['order_id'] = ['required', 'integer'];
  $rules['order_item_id'] = ['required', 'integer'];

  $validator = Validator::make($data, $rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid minimal return' => [[
    'order_id' => 1,
    'order_item_id' => 10,
  ], true],
  'valid full return' => [[
    'order_id' => 1,
    'order_item_id' => 10,
    'description' => 'Product was damaged',
    'quantity' => 2,
  ], true],
  'invalid quantity (zero)' => [[
    'order_id' => 1,
    'order_item_id' => 10,
    'quantity' => 0, // min:1
  ], false],
  'missing order_id' => [[
    'order_item_id' => 10,
  ], false],
  'string quantity' => [[
    'order_id' => 1,
    'order_item_id' => 10,
    'quantity' => 'two',
  ], false],
]);
