<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\GetOrCreateCartRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new GetOrCreateCartRequest())->rules();
});

it('allows an empty request for get or create cart', function () {
  $validator = Validator::make([], $this->rules);

  expect($validator->passes())->toBeTrue();
});

it('verifies that no rules are defined yet', function () {
  // نتحقق أن المصفوفة فارغة لضمان عدم وجود قيود غير مقصودة
  expect($this->rules)->toBeArray()->toBeEmpty();
});
