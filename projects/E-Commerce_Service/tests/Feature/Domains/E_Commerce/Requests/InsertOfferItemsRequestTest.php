<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\InsertOfferItemsRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new InsertOfferItemsRequest())->rules();
  $this->messages = (new InsertOfferItemsRequest())->messages();
});

it('validates items array and its contents', function ($items, $shouldPass) {
  $validator = Validator::make(['items' => $items], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid unique integers' => [[1, 2, 3], true],
  'empty items array'     => [[], false], // مطلوب (required)
  'not an array'          => ['string', false],
  'contains strings'      => [[1, 'abc', 2], false],
  'has duplicates'        => [[1, 2, 2], false], // سيفشل بسبب distinct
]);

it('returns custom error messages correctly', function () {
  // اختبار رسالة التكرار (distinct)
  $validator = Validator::make(
    ['items' => [1, 1]],
    $this->rules,
    $this->messages
  );

  expect($validator->errors()->first('items.0'))
    ->toBe('Duplicate item_id values are not allowed.');

  // اختبار رسالة النوع (integer)
  $validatorInt = Validator::make(
    ['items' => ['not-int']],
    $this->rules,
    $this->messages
  );

  expect($validatorInt->errors()->first('items.0'))
    ->toBe('The item_id must be a valid integer.');
});
