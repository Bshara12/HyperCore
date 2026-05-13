<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\RemoveCartItemsRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new RemoveCartItemsRequest())->rules();
});

it('validates removal of cart items correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid removal' => [[
    'project_id' => 1,
    'items' => [
      ['item_id' => 101],
      ['item_id' => 102],
    ]
  ], true],
  'missing project_id' => [[
    'items' => [['item_id' => 101]]
  ], false],
  'empty items array' => [[
    'project_id' => 1,
    'items' => [] // سيفشل بسبب min:1
  ], false],
  'item_id is not integer' => [[
    'project_id' => 1,
    'items' => [['item_id' => 'abc']]
  ], false],
]);
