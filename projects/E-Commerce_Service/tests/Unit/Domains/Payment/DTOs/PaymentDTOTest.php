<?php

namespace Tests\Unit\Domains\Payment\DTOs;

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Requests\ProcessPaymentRequest;

it('can be instantiated manually with all fields', function () {
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'Ahmed',
    projectId: 10,
    amount: 1000.50,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'one_time',
    toWallet: '0123456789',
    description: 'Test Payment',
    downPayment: 200.0,
    installmentAmount: 100.0,
    totalInstallments: 8,
    intervalDays: 15
  );

  expect($dto->userId)->toBe(1)
    ->and($dto->amount)->toBe(1000.50)
    ->and($dto->intervalDays)->toBe(15);
});

it('creates DTO from request with auth_user attributes', function () {
  // 1. إعداد Request يحتوي على سمات المستخدم (Attributes)
  $request = new ProcessPaymentRequest();

  // محاكاة ما يضعه الـ Middleware في الـ attributes
  $request->attributes->set('auth_user', [
    'id' => 99,
    'name' => 'Zaid'
  ]);

  $request->merge([
    'project_id' => 5,
    'amount' => 500,
    'currency' => 'sar',
    'gateway' => 'paypal',
    'payment_type' => 'installment',
    'down_payment' => 100,
    'installment_amount' => 40,
    'total_installments' => 10,
    'interval_days' => 30
  ]);

  // 2. التنفيذ
  $dto = PaymentDTO::fromRequest($request);

  // 3. التحقق من البيانات والتحويلات
  expect($dto->userId)->toBe(99)
    ->and($dto->userName)->toBe('Zaid')
    ->and($dto->currency)->toBe('SAR') // التأكد من strtoupper
    ->and($dto->paymentType)->toBe('installment')
    ->and($dto->downPayment)->toBe(100.0);
});

it('sets default value for intervalDays when not provided in request', function () {
  $request = new ProcessPaymentRequest();
  $request->attributes->set('auth_user', ['id' => 1, 'name' => 'User']);

  $request->merge([
    'project_id' => 1,
    'amount' => 100,
    'currency' => 'USD',
    'gateway' => 'stripe',
    'payment_type' => 'one_time',
    // 'interval_days' تم إهماله هنا
  ]);

  $dto = PaymentDTO::fromRequest($request);

  // التأكد من أن القيمة الافتراضية هي 30
  expect($dto->intervalDays)->toBe(30);
});

it('handles null and optional fields correctly', function () {
  $request = new ProcessPaymentRequest();
  $request->attributes->set('auth_user', ['id' => 1, 'name' => 'User']);

  $request->merge([
    'project_id' => 1,
    'amount' => 100,
    'currency' => 'USD',
    'gateway' => 'stripe',
    'payment_type' => 'one_time',
    'to_wallet_number' => null,
    'description' => null,
    'down_payment' => 0, // سيتم تحويله لـ null بناءً على الكود الخاص بك
  ]);

  $dto = PaymentDTO::fromRequest($request);

  expect($dto->toWallet)->toBeNull()
    ->and($dto->description)->toBeNull()
    ->and($dto->downPayment)->toBeNull();
});
