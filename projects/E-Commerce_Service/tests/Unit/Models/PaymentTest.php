<?php

namespace Tests\Unit\Models;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

uses(RefreshDatabase::class);

it('verifies payment status helpers', function () {
  $payment = new Payment(['status' => Payment::STATUS_PAID]);

  expect($payment->isPaid())->toBeTrue()
    ->and($payment->isRefunded())->toBeFalse();

  $payment->status = Payment::STATUS_REFUNDED;
  expect($payment->isRefunded())->toBeTrue()
    ->and($payment->isPaid())->toBeFalse();
});

it('casts amount to float', function () {
  $payment = Payment::create([
    'order_id' => 1,
    'user_id' => 1,
    'project_id' => 1,
    'gateway' => 'stripe',
    'amount' => '150.50',
    'currency' => 'USD',
    'status' => 'pending'
  ]);

  expect($payment->amount)->toBeFloat()
    ->and($payment->amount)->toBe(150.5);
});

it('has many transactions and can get the latest one', function () {
  // 1. إنشاء Payment
  $payment = Payment::create([
    'order_id' => 1,
    'user_id' => 1,
    'project_id' => 1,
    'gateway' => 'stripe',
    'amount' => 100.00,
    'currency' => 'USD',
    'status' => 'paid'
  ]);

  // 2. إنشاء العملية الأولى
  Transaction::create([
    'payment_id' => $payment->id,
    'gateway_transaction_id' => 'tx_123',
    'type' => 'charge',
    'amount' => 100.00,
    'status' => 'pending',
    'created_at' => now()->subMinutes(10) // وقت قديم
  ]);

  // 3. إنشاء العملية الأخيرة
  $latest = Transaction::create([
    'payment_id' => $payment->id,
    'gateway_transaction_id' => 'tx_456',
    'type' => 'charge',
    'amount' => 100.00,
    'status' => 'success',
    'created_at' => now() // وقت حديث
  ]);

  // تحديث الكائن لإلغاء أي كاش للعلاقات
  $payment->refresh();

  // التحقق من النتائج
  expect($payment->transactions)->toHaveCount(2)
    // سيعمل هذا لأن Carbon يمتلك منطقاً داخلياً للمقارنة عند استخدام المساواة
    ->and($payment->latestTransaction()->created_at)->toEqual($latest->created_at);
});
