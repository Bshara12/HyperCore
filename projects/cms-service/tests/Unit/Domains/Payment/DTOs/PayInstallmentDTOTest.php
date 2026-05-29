<?php

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\ModelNotFoundException;

// ملاحظة: بما أننا نستخدم المودل، تأكد أن الاختبار يستخدم قاعدة بيانات اختبارات
// يمكنك استخدام trait مثل RefreshDatabase إذا كان إعداد الـ Test الخاص بك يدعمه
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('it creates DTO and finds the wallet when wallet number is provided', function () {
  // 1. إنشاء محفظة وهمية في قاعدة البيانات
  $wallet = Wallet::factory()->create(['wallet_number' => 'W12345']);

  // 2. محاكاة الطلب
  $request = new PayInstallmentRequest();
  $request->merge([
    'payment_id' => 10,
    'gateway' => 'stripe',
    'currency' => 'USD',
    'to_wallet_number' => 'W12345',
  ]);

  // 3. التنفيذ
  $dto = PayInstallmentDTO::fromRequest($request);

  // 4. التحقق
  expect($dto->paymentId)->toBe(10)
    ->and($dto->gateway)->toBe('stripe')
    ->and($dto->toWallet->id)->toBe($wallet->id);
});

test('it creates DTO without wallet when wallet number is not provided', function () {
  $request = new PayInstallmentRequest();
  $request->merge([
    'payment_id' => 20,
    'gateway' => 'paypal',
    'currency' => 'EUR',
  ]);

  $dto = PayInstallmentDTO::fromRequest($request);

  expect($dto->toWallet)->toBeNull();
});

test('it throws ModelNotFoundException when invalid wallet number is provided', function () {
  $request = new PayInstallmentRequest();
  $request->merge([
    'payment_id' => 30,
    'to_wallet_number' => 'INVALID_NUMBER',
  ]);

  // يجب أن يرمي الخطأ لأننا نستخدم firstOrFail()
  expect(fn() => PayInstallmentDTO::fromRequest($request))
    ->toThrow(ModelNotFoundException::class);
});
