<?php

namespace Tests\Unit\Domains\Payment\Requests;

use App\Domains\Payment\Requests\PayInstallmentRequest;

// 1. اختبار: الشروط الأساسية عندما لا تكون بوابة الدفع هي المحفظة (Wallet)
test('it returns only base rules when gateway is not wallet', function () {
  // إنشاء نسخة من الـ Request
  $request = new PayInstallmentRequest();

  // دمج بيانات وهمية في الـ Request (بوابة دفع أخرى مثل stripe)
  $request->merge(['gateway' => 'stripe']);

  // جلب مصفوفة القواعد
  $rules = $request->rules();

  // التأكد من أن القواعد تحتوي فقط على الحقول الأساسية
  expect($rules)->toHaveKey('payment_id')
    ->and($rules)->not->toHaveKey('to_wallet_number')
    ->and($rules['payment_id'])->toBe(['exists:payments,id']);
});

// 2. اختبار: إضافة شرط المحفظة عندما تكون بوابة الدفع هي المحفظة (Wallet)
test('it adds to_wallet_number rule when gateway is wallet', function () {
  $request = new PayInstallmentRequest();

  // دمج بوابة الدفع 'wallet' لمحاكاة مدخلات المستخدم
  $request->merge(['gateway' => 'wallet']);

  $rules = $request->rules();

  // التأكد من إضافة شرط المحفظة بنجاح تماشياً مع الـ if شرطية
  expect($rules)->toHaveKey('payment_id')
    ->and($rules)->toHaveKey('to_wallet_number')
    ->and($rules['payment_id'])->toBe(['exists:payments,id'])
    ->and($rules['to_wallet_number'])->toBe(['exists:wallets,wallet_number']);
});
