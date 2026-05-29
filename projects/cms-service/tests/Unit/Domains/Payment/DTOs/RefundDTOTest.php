<?php

use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Requests\RefundRequest;

test('it creates DTO from array with correct casting', function () {
  $data = [
    'payment_id' => '123', // سترينج ليتم تحويله
    'gateway' => 'stripe',
    'amount' => '50.50',  // سترينج ليتم تحويله
    'currency' => 'eur',  // ليتم تحويله لـ EUR
    'reason' => 'Duplicate charge',
    'transaction_id' => 'tx_999',
    'from_wallet_id' => '5',
    'metadata' => ['key' => 'value']
  ];

  $dto = RefundDTO::fromArray($data);

  expect($dto->paymentId)->toBe(123)
    ->and($dto->amount)->toBe(50.50)
    ->and($dto->currency)->toBe('EUR') // تم التحويل لـ uppercase
    ->and($dto->fromWalletId)->toBe(5) // تم التحويل لـ int
    ->and($dto->metadata)->toBe(['key' => 'value']);
});

test('it creates DTO from request correctly', function () {
  $request = new RefundRequest();
  $request->merge([
    'payment_id' => 100,
    'amount' => 200.0,
    'reason' => 'Customer request'
  ]);

  $dto = RefundDTO::fromRequest($request);

  expect($dto->paymentId)->toBe(100)
    ->and($dto->amount)->toBe(200.0)
    ->and($dto->reason)->toBe('Customer request')
    ->and($dto->gateway)->toBe(''); // كما حددت في الكود الخاص بك
});

test('it handles empty optional fields gracefully', function () {
  $data = [
    'payment_id' => 1,
    'gateway' => 'paypal',
    'amount' => 10.0,
  ];

  $dto = RefundDTO::fromArray($data);

  expect($dto->reason)->toBeNull()
    ->and($dto->transactionId)->toBeNull()
    ->and($dto->fromWalletId)->toBeNull()
    ->and($dto->metadata)->toBe([]); // تأكد من القيمة الافتراضية
});
