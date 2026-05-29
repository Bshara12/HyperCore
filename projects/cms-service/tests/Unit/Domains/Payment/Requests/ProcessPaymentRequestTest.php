<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\ProcessPaymentRequest;

// 1. اختبار الحالة الأولى: بوابة الدفع ليست محفظة (قواعد فارغة)
test('it returns empty rules when gateway is not wallet', function () {
  $request = new ProcessPaymentRequest();

  // دمج بوابة دفع أخرى مثل stripe
  $request->merge(['gateway' => 'stripe']);

  $rules = $request->rules();

  // التأكد من أن مصفوفة الشروط فارغة تماماً كما في الكود
  expect($rules)->toBeEmpty();
});

// 2. اختبار الحالة الثانية: بوابة الدفع هي المحفظة (إضافة شرط toWallet)
test('it adds toWallet rule when gateway is wallet', function () {
  $request = new ProcessPaymentRequest();

  // دمج بوابة الدفع 'wallet' لمحاكاة مدخلات المستخدم
  $request->merge(['gateway' => 'wallet']);

  $rules = $request->rules();

  // التأكد من إضافة شرط المحفظة بنجاح وتغطية البلوك الشرطي
  expect($rules)->toHaveKey('toWallet')
    ->and($rules['toWallet'])->toBe(['exists:wallets,wallet_number']);
});
