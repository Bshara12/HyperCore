<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\ProcessPaymentAction;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  // تجهيز الـ Repository كمحاكي أساسي لاستقبال الموديلات الحقيقية
  $this->repository = Mockery::mock(PaymentRepositoryInterface::class);
  $this->action = new ProcessPaymentAction($this->repository);
});

// ─── اختبارات الدفع عبر المحفظة (Wallet) ───────────────────────────────────

test('it processes full wallet payment successfully', function () {
  // 1. إنشاء بيانات حقيقية في قاعدة البيانات المؤقتة لعدم استخدام الـ Mocks
  $fromWallet = Wallet::factory()->create(['balance' => 500.00]);
  $toWallet = Wallet::factory()->create();

  $dto = new PaymentDTO(
    userId: $fromWallet->user_id,
    userName: 'John Doe',
    projectId: 1,
    amount: 200.00,
    currency: 'USD',
    gateway: 'wallet',
    paymentType: 'full',
    toWallet: $toWallet
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);
  $transaction = Transaction::factory()->make(['id' => 123]);

  // 2. توقع تصرفات الـ Repository بناءً على الموديلات الحقيقية
  $this->repository->shouldReceive('createPayment')->once()->with($dto)->andReturn($payment);
  $this->repository->shouldReceive('findWalletByUserId')->once()->with($dto->userId)->andReturn($fromWallet);
  $this->repository->shouldReceive('debitWallet')->once()->with($fromWallet, 200.00);
  $this->repository->shouldReceive('creditWallet')->once()->with($toWallet, 200.00);
  $this->repository->shouldReceive('createWalletTransaction')->once()->andReturn($transaction);

  $payment->status = Payment::STATUS_PAID;
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PAID)->andReturn($payment);

  // 3. التنفيذ والتحقق
  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_PAID)
    ->and($result['transaction_id'])->toBe(123)
    ->and($result['payment_method'])->toBe('wallet');
});

test('it processes wallet installment payment successfully', function () {
  $fromWallet = Wallet::factory()->create(['balance' => 300.00]);
  $toWallet = Wallet::factory()->create();

  $dto = new PaymentDTO(
    userId: $fromWallet->user_id,
    userName: 'John Doe',
    projectId: 1,
    amount: 1000.00,
    currency: 'USD',
    gateway: 'wallet',
    paymentType: 'installment',
    toWallet: $toWallet,
    downPayment: 150.00,
    installmentAmount: 100.00
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);
  $transaction = Transaction::factory()->make(['id' => 124]);

  $this->repository->shouldReceive('createPayment')->once()->andReturn($payment);
  $this->repository->shouldReceive('findWalletByUserId')->once()->andReturn($fromWallet);
  $this->repository->shouldReceive('createInstallmentPlan')->once()->with($payment, $dto);
  $this->repository->shouldReceive('debitWallet')->once()->with($fromWallet, 150.00); // يجب حسم الدفعة المقدمة أولاً
  $this->repository->shouldReceive('creditWallet')->once()->with($toWallet, 150.00);
  $this->repository->shouldReceive('createWalletTransaction')->once()->andReturn($transaction);

  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PENDING)->andReturn($payment);

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_PENDING)
    ->and($result['installment_number'])->toBe(0); // 0 يعني الدفعة الأولى المقدمة
});

test('it handles wallet exceptions and marks payment as failed', function () {
  $dto = new PaymentDTO(
    userId: 999, // مستخدم غير موجود لا يملك محفظة
    userName: 'Ghost',
    projectId: 1,
    amount: 100.00,
    currency: 'USD',
    gateway: 'wallet',
    paymentType: 'full'
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

  $this->repository->shouldReceive('createPayment')->once()->andReturn($payment);
  $this->repository->shouldReceive('findWalletByUserId')->once()->andReturn(null);

  // التأكد من دخول الـ catch وتحديث الحالة إلى فشل قبل رمي الاستثناء
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_FAILED);

  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class, 'Wallet not found.');
});

test('it throws exception if wallet balance is insufficient', function () {
  $fromWallet = Wallet::factory()->create(['balance' => 5.00]); // رصيد ضعيف جداً

  $dto = new PaymentDTO(
    userId: $fromWallet->user_id,
    userName: 'Poor Guy',
    projectId: 1,
    amount: 100.00,
    currency: 'USD',
    gateway: 'wallet',
    paymentType: 'full'
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

  $this->repository->shouldReceive('createPayment')->once()->andReturn($payment);
  $this->repository->shouldReceive('findWalletByUserId')->once()->andReturn($fromWallet);
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_FAILED);

  expect(fn() => $this->action->execute($dto))->toThrow(\Exception::class);
});


// ─── اختبارات الدفع عبر البوابات الخارجية (Gateway) ───────────────────────

// ─── اختبارات الدفع عبر البوابات الخارجية (Gateway) ───────────────────────

test('it processes full gateway payment successfully', function () {
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'John Doe',
    projectId: 1,
    amount: 300.00,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'full'
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);
  $this->repository->shouldReceive('createPayment')->once()->with($dto)->andReturn($payment);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('charge')->once()->with($dto)->andReturn([
    'success' => true,
    'transaction_id' => 'ch_stripe_123',
    'amount' => 300.00,
    'raw' => ['status' => 'succeeded']
  ]);

  // الحل 1: استخدام bind لضمان تجاوز مشكلة الـ parameters في الحاوية
  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // الحل 2: إزالة الـ Named Arguments لتفادي خطأ Undefined array key 0
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_CHARGE,
    'ch_stripe_123',
    300.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    ['status' => 'succeeded'],
    null
  );

  $payment->status = Payment::STATUS_PAID;
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PAID)->andReturn($payment);

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['payment_method'])->toBe('gateway')
    ->and($result['status'])->toBe(Payment::STATUS_PAID);
});

test('it processes gateway installment payment successfully', function () {
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'John Doe',
    projectId: 1,
    amount: 1200.00,
    currency: 'USD',
    gateway: 'paypal',
    paymentType: 'installment',
    downPayment: 400.00,
    installmentAmount: 200.00
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);

  $this->repository->shouldReceive('createPayment')->once()->andReturn($payment);
  $this->repository->shouldReceive('createInstallmentPlan')->once()->with($payment, $dto);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('chargeAmount')->once()->with($dto, 400.00)->andReturn([
    'success' => true,
    'transaction_id' => 'ch_paypal_555',
    'amount' => 400.00,
    'raw' => ['status' => 'approved']
  ]);

  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // تمرير الوسائط مرتبة رقمياً بدون تسمية لـ Mockery
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_CHARGE,
    'ch_paypal_555',
    400.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    ['status' => 'approved'],
    0
  );

  $payment->status = Payment::STATUS_PENDING;
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_PENDING)->andReturn($payment);

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['installment_number'])->toBe(0)
    ->and($result['status'])->toBe(Payment::STATUS_PENDING);
});

test('it handles gateway failure response correctly', function () {
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'John Doe',
    projectId: 1,
    amount: 100.00,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'full'
  );

  $payment = Payment::factory()->create(['status' => Payment::STATUS_PENDING]);
  $this->repository->shouldReceive('createPayment')->once()->andReturn($payment);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('charge')->once()->with($dto)->andReturn([
    'success' => false,
    'transaction_id' => 'failed_unauthorized',
    'raw' => ['error' => 'card_declined']
  ]);

  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // ترتيب برامترات عادي ومتوافق مع دالة handleGatewayResult
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_CHARGE,
    'failed_unauthorized',
    100.00,
    'USD',
    Transaction::STATUS_FAILED,
    ['error' => 'card_declined'],
    null
  );

  $payment->status = Payment::STATUS_FAILED;
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with($payment, Payment::STATUS_FAILED)->andReturn($payment);

  $result = $this->action->execute($dto);

  expect($result['success'])->toBeFalse()
    ->and($result['status'])->toBe(Payment::STATUS_FAILED);
});
