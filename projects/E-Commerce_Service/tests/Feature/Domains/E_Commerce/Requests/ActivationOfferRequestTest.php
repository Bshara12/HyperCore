<?php

namespace Tests\Feature\Domains\E_Commerce\Requests;

use App\Domains\E_Commerce\Requests\ActivationOfferRequest;
use Illuminate\Support\Facades\Validator;

it('validates is_active is required and boolean', function ($value, $shouldPass) {
  $request = new ActivationOfferRequest();

  $validator = Validator::make(
    ['is_active' => $value],
    $request->rules()
  );

  expect($validator->passes())->toBe($shouldPass);
})->with([
  'true boolean'    => [true, true],
  'false boolean'   => [false, true],
  'integer 1'       => [1, true],
  'integer 0'       => [0, true],
  'string "1"'      => ["1", true],
  'string "0"'      => ["0", true],
  'string "true"'   => ["true", false], // ❌ تم التعديل هنا: لارفيل لا يعتبرها Boolean
  'string text'     => ['not-a-bool', false],
  'null value'      => [null, false],
]);
