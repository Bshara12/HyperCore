<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\UpdateWishlistRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new UpdateWishlistRequest())->rules();
  $this->messages = (new UpdateWishlistRequest())->messages();
});

it('validates partial wishlist updates correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'update only name' => [[
    'name' => 'New Awesome List',
  ], true],
  'update only visibility' => [[
    'visibility' => 'private',
  ], true],
  'update with all fields' => [[
    'name' => 'Tech Gadgets',
    'visibility' => 'public',
    'is_default' => false,
    'is_shareable' => true,
  ], true],
  'invalid visibility' => [[
    'visibility' => 'friends-only',
  ], false],
  'name exceeds limit' => [[
    'name' => str_repeat('a', 256),
  ], false],
  'invalid boolean' => [[
    'is_default' => 'yes',
  ], false],
]);

it('returns custom messages for update failures', function () {
  $validator = Validator::make(
    ['visibility' => 'secret'],
    $this->rules,
    $this->messages
  );

  expect($validator->errors()->first('visibility'))
    ->toBe('Visibility must be either private or public.');
});
