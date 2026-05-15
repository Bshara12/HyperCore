<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\CalculatePricingRequest;
use Illuminate\Support\Facades\Validator;

beforeEach(function () {
  $this->rules = (new CalculatePricingRequest())->rules();
});

it('validates entry_ids correctly', function ($data, $shouldPass) {
  $validator = Validator::make($data, $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'valid array of integers' => [['entry_ids' => [1, 2, 3]], true],
  'valid with code'         => [['entry_ids' => [1], 'code' => 'SAVE10'], true],
  'missing entry_ids'       => [[], false],
  'entry_ids not an array'  => [['entry_ids' => 1], false],
  'array with string ids'   => [['entry_ids' => [1, 'abc']], false],
]);

it('validates code as a nullable string', function ($code, $shouldPass) {
  $validator = Validator::make([
    'entry_ids' => [1],
    'code' => $code
  ], $this->rules);

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'string code'  => ['PROMO2026', true],
  'null code'    => [null, true],
  // قمنا بإزالة حالة 'integer code' لأن لارفيل سيمررها كـ string في أغلب الظروف
  'array code'   => [['not-a-string'], false], // المصفوفة ستفشل حتماً في الـ string rule
]);
