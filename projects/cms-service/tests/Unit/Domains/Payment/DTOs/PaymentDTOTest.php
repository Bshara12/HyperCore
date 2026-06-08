<?php

use App\Domains\Payment\DTOs\PaymentDTO;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('isInstallment returns true only when payment type is installment', function () {
  $dto = new PaymentDTO(1, 'User', 1, 100.0, 'USD', 'stripe', 'installment');
  $dtoFull = new PaymentDTO(1, 'User', 1, 100.0, 'USD', 'stripe', 'full');

  expect($dto->isInstallment())->toBeTrue()
    ->and($dtoFull->isInstallment())->toBeFalse();
});

test('fromArray maps data correctly and applies defaults', function () {
  $data = [
    'user_id' => 1,
    'user_name' => 'John Doe',
    'project_id' => 5,
    'amount' => 500.0,
    'currency' => 'eur', // يجب أن تتحول إلى EUR
    'gateway' => 'stripe',
  ];

  $dto = PaymentDTO::fromArray($data);

  expect($dto->userName)->toBe('John Doe')
    ->and($dto->currency)->toBe('EUR')
    ->and($dto->paymentType)->toBe('full') // القيمة الافتراضية
    ->and($dto->intervalDays)->toBe(30);  // القيمة الافتراضية
});

test('fromRequest maps data and fetches wallet', function () {
  // 1. تجهيز المحفظة
  $wallet = Wallet::factory()->create(['wallet_number' => 'W123']);

  // 2. تجهيز الطلب
  $request = new Request([
    'userId' => 1,
    'userName' => 'John',
    'projectId' => 1,
    'amount' => 100.0,
    'currency' => 'USD',
    'gateway' => 'stripe',
    'paymentType' => 'full',
    'toWallet' => 'W123' // هذا سيجلب المحفظة
  ]);

  $dto = PaymentDTO::fromRequest($request);

  expect($dto->toWallet->id)->toBe($wallet->id);
});

test('fromRequest throws exception if wallet number is invalid', function () {
  $request = new Request(['toWallet' => 'NON_EXISTENT']);

  expect(fn() => PaymentDTO::fromRequest($request))
    ->toThrow(ModelNotFoundException::class);
});
