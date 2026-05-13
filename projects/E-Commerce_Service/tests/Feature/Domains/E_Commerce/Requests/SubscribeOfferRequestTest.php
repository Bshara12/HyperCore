<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\SubscribeOfferRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new SubscribeOfferRequest())->rules();
});

it('validates offer subscription code correctly', function ($code, $shouldPass) {
  $validator = Validator::make(['code' => $code], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid code string' => ['SUMMER2026', true],
  'valid numeric code' => [12345, true],
  'empty code'        => ['', false],
  'null code'         => [null, false],
]);

it('fails if code field is missing entirely', function () {
  $validator = Validator::make([], $this->rules);

  expect($validator->passes())->toBeFalse();
  expect($validator->errors()->has('code'))->toBeTrue();
});
