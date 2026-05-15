<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\ReorderWishlistItemsRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new ReorderWishlistItemsRequest())->rules();
  $this->messages = (new ReorderWishlistItemsRequest())->messages();
});

it('validates wishlist reordering correctly', function ($items, $shouldPass) {
  $validator = Validator::make(['items' => $items], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid reorder' => [[
    ['id' => 1, 'sort_order' => 0],
    ['id' => 5, 'sort_order' => 1],
  ], true],
  'missing sort_order' => [[
    ['id' => 1]
  ], false],
  'negative sort_order' => [[
    ['id' => 1, 'sort_order' => -1]
  ], false],
  'empty array' => [[], false],
  'invalid id format' => [[
    ['id' => 'abc', 'sort_order' => 2]
  ], false],
]);

it('returns custom messages for reordering failures', function () {
  $validator = Validator::make(
    ['items' => [['sort_order' => 1]]], // مفقود الـ id
    $this->rules,
    $this->messages
  );

  expect($validator->errors()->first('items.0.id'))
    ->toBe('Each item must contain an id.');

  $validatorMissingOrder = Validator::make(
    ['items' => [['id' => 1]]],
    $this->rules,
    $this->messages
  );

  expect($validatorMissingOrder->errors()->first('items.0.sort_order'))
    ->toBe('Each item must contain sort_order.');
});
