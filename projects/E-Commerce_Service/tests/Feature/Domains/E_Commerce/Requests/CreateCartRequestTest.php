<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CreateCartRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CreateCartRequest())->rules();
});

it('validates items array structure', function ($items, $shouldPass) {
  $validator = Validator::make(['items' => $items], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid items'          => [[['item_id' => 1, 'quantity' => 2]], true],
  'multiple valid items' => [[['item_id' => 1, 'quantity' => 1], ['item_id' => 2, 'quantity' => 5]], true],
  'empty items array'    => [[], false], // بسبب min:1
  'not an array'         => ['string-instead-of-array', false],
  'missing items field'  => [null, false],
]);

it('validates individual item fields', function ($itemData, $shouldPass) {
  // نختبر عنصر واحد داخل المصفوفة
  $validator = Validator::make(['items' => [$itemData]], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid item'            => [['item_id' => 10, 'quantity' => 1], true],
  'missing quantity'      => [['item_id' => 10], false],
  'zero quantity'         => [['item_id' => 10, 'quantity' => 0], false], // بسبب min:1
  'negative quantity'     => [['item_id' => 10, 'quantity' => -1], false],
  'non-integer item_id'   => [['item_id' => 'abc', 'quantity' => 1], false],
  'string quantity'       => [['item_id' => 10, 'quantity' => 'high'], false],
]);
