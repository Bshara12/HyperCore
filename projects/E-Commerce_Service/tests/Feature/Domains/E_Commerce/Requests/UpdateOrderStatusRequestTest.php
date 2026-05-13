<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\UpdateOrderStatusRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new UpdateOrderStatusRequest())->rules();
});

it('validates order status correctly', function ($status, $shouldPass) {
  $validator = Validator::make(['status' => $status], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid pending'  => ['pending', true],
  'valid shipped'  => ['shipped', true],
  'invalid status' => ['on-the-way', false],
  'empty status'   => ['', false],
  'numeric status' => [123, false],
]);
