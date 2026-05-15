<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\UpdateCartRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new UpdateCartRequest())->rules();
});

it('validates cart update items correctly', function ($items, $shouldPass) {
  $validator = Validator::make(['items' => $items], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid update'         => [[['item_id' => 1, 'quantity' => 5]], true],
  'multiple valid items' => [[['item_id' => 1, 'quantity' => 2], ['item_id' => 2, 'quantity' => 10]], true],
  'empty items array'    => [[], false], // min:1
  'quantity is zero'     => [[['item_id' => 1, 'quantity' => 0]], false], // min:1
  'missing item_id'      => [[['quantity' => 5]], false],
  'non-integer quantity' => [[['item_id' => 1, 'quantity' => 'two']], false],
]);

it('requires the items field to be an array', function () {
  $validator = Validator::make(['items' => 'not-an-array'], $this->rules);

  expect($validator->passes())->toBeFalse();
  expect($validator->errors()->has('items'))->toBeTrue();
});
