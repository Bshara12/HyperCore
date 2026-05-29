<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\TopUpWalletRequest;

test('it returns correct validation rules for top up wallet request', function () {
  // إنشاء نسخة من الـ Request
  $request = new TopUpWalletRequest();

  // جلب القواعد
  $rules = $request->rules();

  // التأكد من وجود كافة القواعد المطلوبة وتطابقها
  expect($rules)->toHaveKeys(['wallet_number', 'amount', 'note'])
    ->and($rules['wallet_number'])->toBe(['required'])
    ->and($rules['amount'])->toBe(['required', 'numeric', 'min:0.01'])
    ->and($rules['note'])->toBe(['nullable', 'string', 'max:255']);
});
