<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\RefundRequest;

test('it returns correct rules for refund request', function () {
  // إنشاء نسخة من الـ Request
  $request = new RefundRequest();

  // جلب القواعد
  $rules = $request->rules();

  // التأكد من أن جميع الحقول المطلوبة موجودة وبنفس الإعدادات
  expect($rules)->toHaveKeys(['payment_id', 'amount', 'reason'])
    ->and($rules['payment_id'])->toBe(['required', 'integer', 'exists:payments,id'])
    ->and($rules['amount'])->toBe(['required', 'numeric', 'min:0.01'])
    ->and($rules['reason'])->toBe(['nullable', 'string', 'max:500']);
});
