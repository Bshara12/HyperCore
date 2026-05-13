<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\StoreWishlistItemRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new StoreWishlistItemRequest())->rules();
});

it('validates wishlist item storage correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid basic entry' => [[
    'product_id' => 50,
  ], true],
  'valid entry with variant and flags' => [[
    'product_id' => 50,
    'variant_id' => 5,
    'added_from_cart' => true,
    'notify_on_price_drop' => false,
    'notify_on_back_in_stock' => true,
  ], true],
  'missing product_id' => [[
    'variant_id' => 5,
  ], false],
  'invalid boolean flag' => [[
    'product_id' => 50,
    'added_from_cart' => 'not-a-boolean',
  ], false],
  'zero or negative product_id' => [[
    'product_id' => 0, // min:1
  ], false],
]);

it('allows nullable variant_id and boolean flags', function () {
  $data = [
    'product_id' => 10,
    'variant_id' => null,
    'added_from_cart' => null,
  ];

  $validator = Validator::make($data, $this->rules);
  expect($validator->passes())->toBeTrue();
});
