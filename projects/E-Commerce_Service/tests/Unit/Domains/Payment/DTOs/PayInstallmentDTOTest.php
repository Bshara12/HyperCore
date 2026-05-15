<?php

namespace Tests\Unit\Domains\Payment\DTOs;

use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\Requests\PayInstallmentRequest;

it('can be instantiated manually', function () {
  $dto = new PayInstallmentDTO(
    payment_id: 1,
    gateway: 'stripe',
    currency: 'USD',
    to_wallet_number: '0123456789'
  );

  expect($dto->payment_id)->toBe(1)
    ->and($dto->gateway)->toBe('stripe')
    ->and($dto->currency)->toBe('USD')
    ->and($dto->to_wallet_number)->toBe('0123456789');
});

it('can be created from a request object', function () {
  // 1. إنشاء Request وهمي
  $request = new PayInstallmentRequest();

  // محاكاة البيانات في الـ Request
  $request->merge([
    'payment_id' => 50,
    'gateway' => 'paypal',
    'currency' => 'usd', // سنمررها بصيغة small للتأكد من تحويلها لـ capital
    'to_wallet_number' => '9876543210'
  ]);

  // 2. التحويل من Request إلى DTO
  $dto = PayInstallmentDTO::fromRequest($request);

  // 3. التحقق
  expect($dto->payment_id)->toBe(50)
    ->and($dto->gateway)->toBe('paypal')
    ->and($dto->currency)->toBe('USD') // التأكد من عمل strtoupper
    ->and($dto->to_wallet_number)->toBe('9876543210');
});

it('handles optional to_wallet_number correctly', function () {
  $request = new PayInstallmentRequest();
  $request->merge([
    'payment_id' => 10,
    'gateway' => 'fawry',
    'currency' => 'egp',
  ]);

  $dto = PayInstallmentDTO::fromRequest($request);

  expect($dto->to_wallet_number)->toBeNull();
});
