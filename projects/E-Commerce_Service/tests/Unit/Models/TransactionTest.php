<?php

namespace Tests\Unit\Models;

use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('belongs to a payment', function () {
  // 1. إنشاء الدفع الأب لتلبية قيد foreign key
  $payment = Payment::create([
    'order_id' => 1,
    'user_id' => 1,
    'project_id' => 1,
    'gateway' => 'stripe',
    'amount' => 100,
    'currency' => 'USD',
    'status' => 'paid'
  ]);

  // 2. إنشاء العملية
  $transaction = Transaction::create([
    'payment_id' => $payment->id,
    'gateway_transaction_id' => 'ch_test_123',
    'type' => Transaction::TYPE_CHARGE,
    'amount' => 100,
    'currency' => 'USD',
    'status' => Transaction::STATUS_SUCCESS,
  ]);

  // 3. التحقق من العلاقة
  expect($transaction->payment)->toBeInstanceOf(Payment::class)
    ->and($transaction->payment->id)->toBe($payment->id);
});

it('verifies transaction helper methods', function () {
  $transaction = new Transaction([
    'status' => Transaction::STATUS_SUCCESS,
    'type' => Transaction::TYPE_CHARGE
  ]);

  expect($transaction->isSuccess())->toBeTrue()
    ->and($transaction->isCharge())->toBeTrue()
    ->and($transaction->isRefund())->toBeFalse();

  $transaction->type = Transaction::TYPE_REFUND;
  expect($transaction->isRefund())->toBeTrue()
    ->and($transaction->isCharge())->toBeFalse();
});

it('correctly casts attributes', function () {
  // 1. يجب إنشاء Payment أولاً لأن جدول transactions مرتبط به بـ Foreign Key
  $payment = Payment::create([
    'order_id' => 1,
    'user_id' => 1,
    'project_id' => 1,
    'gateway' => 'stripe',
    'amount' => 100,
    'currency' => 'USD',
    'status' => 'pending'
  ]);

  $now = now()->roundUnit('second'); // نستخدم roundUnit لتجنب فروقات الملي ثانية عند المقارنة

  $transaction = Transaction::create([
    'payment_id' => $payment->id, // نستخدم المعرف الحقيقي هنا
    'gateway_transaction_id' => 'tx_999',
    'type' => 'charge',
    'amount' => '250.75',
    'currency' => 'USD',
    'status' => 'success',
    'gateway_response' => ['id' => 'res_1', 'message' => 'Approved'],
    'processed_at' => $now,
  ]);

  // التحقق من التحويلات (Casting)
  expect($transaction->amount)->toBeFloat()
    ->and($transaction->amount)->toBe(250.75)
    ->and($transaction->gateway_response)->toBeArray()
    ->and($transaction->gateway_response['message'])->toBe('Approved')
    ->and($transaction->processed_at)->toBeInstanceOf(Carbon::class)
    ->and($transaction->processed_at->toDateTimeString())->toBe($now->toDateTimeString());
});
