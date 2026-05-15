<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\RemoveOfferItemsRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new RemoveOfferItemsRequest())->rules();
  $this->messages = (new RemoveOfferItemsRequest())->messages();
});

it('validates removal of offer items array', function ($items, $shouldPass) {
  $validator = Validator::make(['items' => $items], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid unique IDs'     => [[10, 20, 30], true],
  'empty array'          => [[], false], // مطلوب
  'duplicate IDs'        => [[10, 10], false], // بفضل distinct
  'non-integer values'   => [[10, 'invalid'], false],
]);

it('returns custom messages for removal errors', function () {
  $validator = Validator::make(
    ['items' => [5, 5]],
    $this->rules,
    $this->messages
  );

  // التحقق من رسالة التكرار عند الحذف
  expect($validator->errors()->first('items.0'))
    ->toBe('Duplicate item_id values are not allowed.');

  $validatorMissing = Validator::make(
    ['items' => [null]],
    $this->rules,
    $this->messages
  );

  expect($validatorMissing->errors()->first('items.0'))
    ->toBe('Each item must have an item_id.');
});
