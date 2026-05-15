<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\UpdateReturnRequestRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new UpdateReturnRequestRequest())->rules();
});

it('validates return request status update correctly', function ($status, $shouldPass) {
  $validator = Validator::make(['status' => $status], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid approved' => ['approved', true],
  'valid rejected' => ['rejected', true],
  'invalid status' => ['pending', false], // مسموح فقط بـ approved/rejected
  'empty status'   => ['', false],
  'numeric status' => [1, false],
]);
