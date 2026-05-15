<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\AddWishlistItemRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new AddWishlistItemRequest())->rules();
  $this->messages = (new AddWishlistItemRequest())->messages();
});

it('validates product_id correctly', function ($id, $shouldPass) {
  $validator = Validator::make(
    ['product_id' => $id],
    ['product_id' => $this->rules['product_id']]
  );

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid integer' => [10, true],
  'zero value'    => [0, false], // بسبب min:1
  'negative'      => [-5, false],
  'string text'   => ['abc', false],
  'null value'    => [null, false],
]);

it('validates variant_id as nullable and positive integer', function ($id, $shouldPass) {
  $validator = Validator::make(
    ['variant_id' => $id],
    ['variant_id' => $this->rules['variant_id']]
  );

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'is null'       => [null, true], // لأنه nullable
  'valid integer' => [5, true],
  'zero value'    => [0, false],
  'negative'      => [-1, false],
]);

it('returns custom error messages for product_id', function () {
  $validator = Validator::make(
    ['product_id' => null],
    ['product_id' => $this->rules['product_id']],
    $this->messages
  );

  $errors = $validator->errors();

  expect($errors->first('product_id'))->toBe('Product id is required.');

  $validatorText = Validator::make(
    ['product_id' => 'not-int'],
    ['product_id' => $this->rules['product_id']],
    $this->messages
  );

  expect($validatorText->errors()->first('product_id'))->toBe('Product id must be an integer.');
});
