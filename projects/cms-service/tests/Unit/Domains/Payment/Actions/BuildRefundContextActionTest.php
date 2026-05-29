<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\BuildRefundContextAction;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->action = new BuildRefundContextAction();
});

test('it successfully builds refund context with valid transaction', function () {
  // 1. إنشاء الدفع
  $payment = Payment::factory()->create([
    'gateway' => 'stripe',
    'currency' => 'USD'
  ]);

  // 2. إنشاء المحافظ المطلوبة أولاً لتجنب خطأ الـ Foreign Key
  // افترضت وجود موديل ومصنع باسم Wallet، تأكد من تعديل الأسماء إذا كانت مختلفة
  $fromWallet = \App\Models\Wallet::factory()->create();
  $toWallet = \App\Models\Wallet::factory()->create();

  // 3. إنشاء معاملة ناجحة باستخدام IDs المحافظ التي أنشأناها
  $transaction = Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_CHARGE,
    'status' => Transaction::STATUS_SUCCESS,
    'gateway_transaction_id' => 'tx_abc_123',
    'from_wallet_id' => $fromWallet->id,
    'to_wallet_id' => $toWallet->id,
  ]);

  // 4. تحضير الـ DTO
  $dto = new RefundDTO(
    paymentId: $payment->id,
    gateway: 'stripe',
    amount: 100.0,
    currency: 'USD',
    reason: 'Refund test'
  );

  // 5. تنفيذ الأكشن
  $result = $this->action->execute($payment, $dto);

  // 6. التحقق من النتائج
  expect($result)->toBeInstanceOf(RefundDTO::class)
    ->and($result->transactionId)->toBe('tx_abc_123')
    ->and($result->fromWalletId)->toBe($fromWallet->id)
    ->and($result->toWalletId)->toBe($toWallet->id);
});

test('it throws exception when no successful transaction exists', function () {
  $payment = Payment::factory()->create();

  // نرسل DTO بدون معاملة مرتبطة
  $dto = new RefundDTO(
    paymentId: $payment->id,
    gateway: 'stripe',
    amount: 100.0,
    currency: 'USD'
  );

  expect(fn() => $this->action->execute($payment, $dto))
    ->toThrow(\Exception::class, 'No successful transaction found.');
});

test('it picks the latest transaction if multiple exist', function () {
  $payment = Payment::factory()->create();

  // معاملة قديمة
  Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_CHARGE,
    'status' => Transaction::STATUS_SUCCESS,
    'gateway_transaction_id' => 'old_tx',
    'created_at' => now()->subDays(2),
  ]);

  // معاملة حديثة
  $latestTransaction = Transaction::factory()->create([
    'payment_id' => $payment->id,
    'type' => Transaction::TYPE_CHARGE,
    'status' => Transaction::STATUS_SUCCESS,
    'gateway_transaction_id' => 'new_tx',
    'created_at' => now()->subDay(),
  ]);

  $dto = new RefundDTO(
    paymentId: $payment->id,
    gateway: 'stripe',
    amount: 100.0,
    currency: 'USD'
  );

  $result = $this->action->execute($payment, $dto);

  expect($result->transactionId)->toBe('new_tx');
});
