<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\GenerateWishlistShareLinkRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new GenerateWishlistShareLinkRequest())->rules();
});

it('allows an empty request as there are no rules defined', function () {
  $validator = Validator::make([], $this->rules);

  expect($validator->passes())->toBeTrue();
});

it('has an empty rules array', function () {
  expect($this->rules)->toBeArray()->toBeEmpty();
});
