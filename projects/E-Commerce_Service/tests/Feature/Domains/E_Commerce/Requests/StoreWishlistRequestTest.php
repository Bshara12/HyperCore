<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\StoreWishlistRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new StoreWishlistRequest())->rules();
  $this->messages = (new StoreWishlistRequest())->messages();
});

it('validates wishlist creation correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid full data' => [[
    'name' => 'My Summer Favorites',
    'visibility' => 'public',
    'is_default' => true,
    'is_shareable' => true,
  ], true],
  'valid minimal data' => [[
    'name' => 'Birthdays',
  ], true],
  'missing name' => [[
    'visibility' => 'private',
  ], false],
  'invalid visibility value' => [[
    'name' => 'Tech List',
    'visibility' => 'friends-only', // غير موجود في Rule::in
  ], false],
  'name exceeds limit' => [[
    'name' => str_repeat('a', 256),
  ], false],
]);

it('returns custom error messages for wishlist creation', function () {
  $validator = Validator::make(
    ['visibility' => 'hidden'],
    $this->rules,
    $this->messages
  );

  // التحقق من الرسالة المخصصة للرؤية
  expect($validator->errors()->first('visibility'))
    ->toBe('Visibility must be either private or public.');

  // التحقق من رسالة الاسم المطلوبة
  expect($validator->errors()->first('name'))
    ->toBe('Wishlist name is required.');
});
