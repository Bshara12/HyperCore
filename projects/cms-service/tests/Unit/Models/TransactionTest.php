<?php

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it identifies status and method correctly using helpers', function () {
  $transaction = Transaction::factory()->create([
    'status' => Transaction::STATUS_SUCCESS,
    'payment_method' => Transaction::METHOD_GATEWAY,
    'installment_number' => 0
  ]);

  expect($transaction->isSuccess())->toBeTrue()
    ->and($transaction->isGateway())->toBeTrue()
    ->and($transaction->isWallet())->toBeFalse()
    ->and($transaction->isDownPayment())->toBeTrue();
});

test('it handles non-down-payment installments', function () {
  $transaction = Transaction::factory()->create(['installment_number' => 2]);

  expect($transaction->isDownPayment())->toBeFalse();
});

test('it has correct relationships', function () {
  $fromWallet = Wallet::factory()->create();
  $toWallet = Wallet::factory()->create();

  $transaction = Transaction::factory()->create([
    'from_wallet_id' => $fromWallet->id,
    'to_wallet_id' => $toWallet->id,
  ]);

  expect($transaction->fromWallet->id)->toBe($fromWallet->id)
    ->and($transaction->toWallet->id)->toBe($toWallet->id);
});

test('it casts gateway_response to array', function () {
  $data = ['ref' => '12345', 'status' => 'approved'];
  $transaction = Transaction::factory()->create(['gateway_response' => $data]);

  expect($transaction->gateway_response)->toBeArray()
    ->and($transaction->gateway_response['ref'])->toBe('12345');
});
test('it identifies all transaction helpers states correctly', function () {
  // اختبار الحالة "الفاشلة" و "المحفظة"
  $transaction = Transaction::factory()->create([
    'status' => Transaction::STATUS_FAILED,
    'payment_method' => Transaction::METHOD_WALLET,
  ]);

  expect($transaction->isSuccess())->toBeFalse()
    ->and($transaction->isWallet())->toBeTrue()
    ->and($transaction->isGateway())->toBeFalse();
});

test('it identifies refund type', function () {
  $transaction = Transaction::factory()->create(['type' => Transaction::TYPE_REFUND]);

  expect($transaction->type)->toBe(Transaction::TYPE_REFUND);
});

test('it has correct relationships and handles null relationships', function () {
  $transaction = Transaction::factory()->create([
    'from_wallet_id' => null,
    'to_wallet_id' => null,
  ]);

  // التأكد من أن العلاقات تعيد null عند عدم وجود بيانات، لضمان تغطية مسار الـ null
  expect($transaction->fromWallet)->toBeNull()
    ->and($transaction->toWallet)->toBeNull();
});

test('it casts processed_at to datetime', function () {
  $now = now();
  $transaction = Transaction::factory()->create(['processed_at' => $now]);

  expect($transaction->processed_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($transaction->processed_at->toDateTimeString())->toBe($now->toDateTimeString());
});
test('it belongs to a payment', function () {
  // 1. إنشاء Payment
  $payment = \App\Models\Payment::factory()->create();

  // 2. إنشاء Transaction مرتبطة بهذا الـ Payment
  $transaction = Transaction::factory()->create([
    'payment_id' => $payment->id
  ]);

  // 3. التأكد من أن العلاقة تعيد كائن من نوع Payment وأن المعرف مطابق
  expect($transaction->payment)->toBeInstanceOf(\App\Models\Payment::class)
    ->and($transaction->payment->id)->toBe($payment->id);

  // 4. التأكد من أن استدعاء الدالة كـ Relationship (Builder) يعمل أيضاً
  expect($transaction->payment())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
});
