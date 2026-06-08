<?php

use App\Domains\Payment\DTOs\TopUpDTO;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it creates DTO successfully when wallet exists', function () {
  // 1. إنشاء محفظة في قاعدة البيانات
  $wallet = Wallet::factory()->create(['wallet_number' => 'W123']);

  // 2. إعداد الطلب
  $request = new Request([
    'wallet_number' => 'W123',
    'amount' => 500,
    'note' => 'Monthly top up'
  ]);

  // 3. التنفيذ
  $dto = TopUpDTO::fromRequest($request);

  // 4. التحقق
  expect($dto->wallet->id)->toBe($wallet->id)
    ->and($dto->amount)->toBe(500)
    ->and($dto->note)->toBe('Monthly top up');
});

test('it throws exception when wallet is not found', function () {
  // إعداد طلب برقم محفظة غير موجود
  $request = new Request([
    'wallet_number' => 'INVALID_NUMBER',
    'amount' => 100
  ]);

  // التحقق من أن الكود يرمي الاستثناء الصحيح
  expect(fn() => TopUpDTO::fromRequest($request))
    ->toThrow(\Exception::class, 'Wallet not found with number: INVALID_NUMBER');
});

test('it handles null note correctly', function () {
  $wallet = Wallet::factory()->create(['wallet_number' => 'W123']);

  // إرسال طلب بدون ملاحظة
  $request = new Request([
    'wallet_number' => 'W123',
    'amount' => 200
  ]);

  $dto = TopUpDTO::fromRequest($request);

  expect($dto->note)->toBeNull();
});
