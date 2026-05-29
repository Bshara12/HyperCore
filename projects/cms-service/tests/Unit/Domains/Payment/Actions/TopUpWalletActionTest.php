<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\TopUpWalletAction;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = Mockery::mock(PaymentRepositoryInterface::class);
  $this->action = new TopUpWalletAction($this->repository);
});

// اختبار: شحن المحفظة بنجاح وتحديث الرصيد وإنشاء المعاملة
test('it tops up the wallet successfully and creates a charge transaction', function () {
  // 1. إنشاء محفظة حقيقية في قاعدة البيانات برصيد ابتدائي 100
  $wallet = Wallet::factory()->create([
    'balance' => 100.00
  ]);

  // 2. تجهيز كائن الـ DTO بشكل مبسط متوافق مع استقبال كود الـ Action
  $dto = (object) [
    'wallet' => $wallet,
    'amount' => 50.00,
  ];

  // 3. محاكاة دالة شحن المحفظة (نقوم بزيادة الرصيد حقيقياً ليتوافق مع دالة fresh)
  $this->repository->shouldReceive('creditWallet')
    ->once()
    ->with($wallet, 50.00)
    ->andReturnUsing(function ($wallet, $amount) {
      $wallet->increment('balance', $amount);
    });

  // 4. محاكاة إنشاء سجل المعاملة وإرجاع كائن Transaction حقيقي لتجنب الـ TypeError
  $this->repository->shouldReceive('createWalletTransaction')->once()->with(
    null,                        // payment
    Transaction::TYPE_CHARGE,    // type
    null,                        // fromWalletId
    $wallet->id,                 // toWalletId
    50.00,                       // amount
    'USD',                       // currency
    Transaction::STATUS_SUCCESS, // status
    null                         // installmentNumber
  )->andReturnUsing(function () {
    return Transaction::factory()->create([
      'type' => Transaction::TYPE_CHARGE,
      'status' => Transaction::STATUS_SUCCESS,
      'amount' => 50.00
    ]);
  });

  // 5. تنفيذ الـ Action
  $result = $this->action->execute($dto);

  // 6. التأكيدات (Assertions)
  expect($result['wallet_id'])->toBe($wallet->id)
    ->and($result['amount_added'])->toBe(50.00)
    ->and($result['new_balance'])->toBe(150.00) // 100 القديمة + 50 الشحن الجديد
    ->and($result['transaction_id'])->not->toBeNull(); // التعديل هنا
});
