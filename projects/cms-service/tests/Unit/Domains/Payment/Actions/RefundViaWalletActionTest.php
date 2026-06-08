<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\RefundViaWalletAction;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet; // تأكد من وجود هذا السطر في الأعلى
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = Mockery::mock(PaymentRepositoryInterface::class);
  $this->action = new RefundViaWalletAction($this->repository);
});

// 1. اختبار: نجاح استرداد كامل المبلغ إلى المحفظة وتحديث حالة الدفع
test('it refunds full amount successfully via wallet and updates payment status', function () {
  $payment = Payment::factory()->create([
    'user_id' => 1,
    'amount' => 150.00,
    'status' => Payment::STATUS_PAID
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'wallet',
    'amount' => 150.00,
    'currency' => 'USD',
    'from_wallet_id' => 5,
  ]);

  // الحل: إنشاء محفظة حقيقية باستخدام الـ Factory لتجاوز الـ TypeError
  $wallet = Wallet::factory()->create(['user_id' => 1]);

  $this->repository->shouldReceive('findWalletByUserId')->once()->with(1)->andReturn($wallet);
  $this->repository->shouldReceive('creditWallet')->once()->with($wallet, 150.00);

  $this->repository->shouldReceive('createWalletTransaction')->once()->with(
    $payment,
    Transaction::TYPE_REFUND,
    5,
    $wallet->id, // الاعتماد على الـ id الحقيقي للمحفظة
    150.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    null
  )->andReturnUsing(function ($payment) {
    return Transaction::factory()->create([
      'payment_id' => $payment->id,
      'type' => Transaction::TYPE_REFUND,
      'status' => Transaction::STATUS_SUCCESS,
      'amount' => 150.00
    ]);
  });

  $this->repository->shouldReceive('updatePaymentStatus')->once()->with(
    $payment,
    Payment::STATUS_REFUNDED
  )->andReturnUsing(function ($payment, $status) {
    $payment->update(['status' => $status]);
    return $payment;
  });

  $result = $this->action->execute($payment, $dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_REFUNDED)
    ->and($result['payment_method'])->toBe('wallet');
});

// 2. اختبار: استرداد جزئي للمبلغ إلى المحفظة دون تغيير حالة الدفع الإجمالية
test('it processes partial refund successfully via wallet without changing overall status', function () {
  $payment = Payment::factory()->create([
    'user_id' => 1,
    'amount' => 200.00,
    'status' => Payment::STATUS_PAID
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'wallet',
    'amount' => 70.00,
    'currency' => 'USD',
    'from_wallet_id' => 5,
  ]);

  // الحل هنا أيضاً: محفظة حقيقية متوافقة مع الـ Type Hint
  $wallet = Wallet::factory()->create(['user_id' => 1]);

  $this->repository->shouldReceive('findWalletByUserId')->once()->with(1)->andReturn($wallet);
  $this->repository->shouldReceive('creditWallet')->once()->with($wallet, 70.00);

  $this->repository->shouldReceive('createWalletTransaction')->once()->with(
    $payment,
    Transaction::TYPE_REFUND,
    5,
    $wallet->id,
    70.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    null
  )->andReturnUsing(function ($payment) {
    return Transaction::factory()->create([
      'payment_id' => $payment->id,
      'type' => Transaction::TYPE_REFUND,
      'status' => Transaction::STATUS_SUCCESS,
      'amount' => 70.00
    ]);
  });

  $this->repository->shouldNotReceive('updatePaymentStatus');

  $result = $this->action->execute($payment, $dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_PAID);
});

// 3. اختبار: إطلاق استثناء (Exception) في حال عدم العثور على محفظة المستخدم
test('it throws an exception if the user wallet is not found', function () {
  $payment = Payment::factory()->create(['user_id' => 999]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'wallet',
    'amount' => 50.00,
    'currency' => 'USD',
  ]);

  $this->repository->shouldReceive('findWalletByUserId')->once()->with(999)->andReturn(null);

  expect(fn() => $this->action->execute($payment, $dto))
    ->toThrow(\Exception::class, 'Wallet not found.');
});
