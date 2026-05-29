<?php

namespace Tests\Unit\Domains\Payment\Actions;

use App\Domains\Payment\Actions\RefundViaGatewayAction;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\Gateways\PaymentGatewayInterface;
use App\Domains\Payment\Repositories\PaymentRepositoryInterface;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = Mockery::mock(PaymentRepositoryInterface::class);
  $this->action = new RefundViaGatewayAction($this->repository);
});

// 1. اختبار: نجاح استرداد المبلغ بالكامل وتحديث حالة الدفع إلى REFUNDED
test('it refunds full amount successfully via gateway and updates payment status', function () {
  $payment = Payment::factory()->create([
    'amount' => 100.00,
    'status' => Payment::STATUS_PAID
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 100.00,
    'currency' => 'USD',
    'reason' => 'Customer request'
  ]);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('refund')->once()->with($dto)->andReturn([
    'success' => true,
    'refund_id' => 'ref_stripe_123',
    'raw' => ['status' => 'succeeded']
  ]);

  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // إضافة return هنا لحل مشكلة الـ TypeError
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_REFUND,
    'ref_stripe_123',
    100.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    ['status' => 'succeeded'],
    null
  )->andReturnUsing(function ($payment) {
    return Transaction::factory()->create([
      'payment_id' => $payment->id,
      'type' => Transaction::TYPE_REFUND,
      'status' => Transaction::STATUS_SUCCESS,
      'amount' => 100.00
    ]);
  });

  // تعديل هذا الجزء في الاختبار الأول
  $this->repository->shouldReceive('updatePaymentStatus')->once()->with(
    $payment,
    Payment::STATUS_REFUNDED
  )->andReturnUsing(function ($payment, $status) {
    $payment->update(['status' => $status]);
    return $payment; // أضفنا return هنا لضمان إرجاع كائن الـ Payment
  });

  $result = $this->action->execute($payment, $dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_REFUNDED)
    ->and($result['refund_id'])->toBe('ref_stripe_123');
});

// 2. اختبار: استرداد جزئي للمبلغ (لا يغير حالة الدفع الإجمالية لـ REFUNDED)
test('it processes partial refund successfully without changing overall payment status', function () {
  $payment = Payment::factory()->create([
    'amount' => 100.00,
    'status' => Payment::STATUS_PAID
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'paypal',
    'amount' => 40.00,
    'currency' => 'USD',
  ]);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('refund')->once()->with($dto)->andReturn([
    'success' => true,
    'refund_id' => 'ref_paypal_456',
    'raw' => ['status' => 'completed']
  ]);

  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // إضافة return هنا أيضاً لحل مشكلة الـ TypeError
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_REFUND,
    'ref_paypal_456',
    40.00,
    'USD',
    Transaction::STATUS_SUCCESS,
    ['status' => 'completed'],
    null
  )->andReturnUsing(function ($payment) {
    return Transaction::factory()->create([
      'payment_id' => $payment->id,
      'type' => Transaction::TYPE_REFUND,
      'status' => Transaction::STATUS_SUCCESS,
      'amount' => 40.00
    ]);
  });

  $this->repository->shouldNotReceive('updatePaymentStatus');

  $result = $this->action->execute($payment, $dto);

  expect($result['success'])->toBeTrue()
    ->and($result['status'])->toBe(Payment::STATUS_PAID)
    ->and($result['refund_id'])->toBe('ref_paypal_456');
});

// 3. اختبار: فشل عملية الاسترداد من جهة بوابة الدفع الخارجية
test('it handles failed gateway refund response correctly', function () {
  $payment = Payment::factory()->create([
    'amount' => 50.00,
    'status' => Payment::STATUS_PAID
  ]);

  $dto = RefundDTO::fromArray([
    'payment_id' => $payment->id,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
  ]);

  $gatewayMock = Mockery::mock(PaymentGatewayInterface::class);
  $gatewayMock->shouldReceive('refund')->once()->with($dto)->andReturn([
    'success' => false,
    'refund_id' => 'ref_failed_000',
    'raw' => ['error' => 'insufficient_gateway_funds']
  ]);

  app()->bind(PaymentGatewayInterface::class, function () use ($gatewayMock) {
    return $gatewayMock;
  });

  // تأمين الإرجاع بـ Transaction فارغ تماشياً مع الـ Return Type الخاص بالـ Repository
  $this->repository->shouldReceive('createGatewayTransaction')->once()->with(
    $payment,
    Transaction::TYPE_REFUND,
    'ref_failed_000',
    50.00,
    'USD',
    Transaction::STATUS_FAILED,
    ['error' => 'insufficient_gateway_funds'],
    null
  )->andReturn(new Transaction());

  $this->repository->shouldNotReceive('updatePaymentStatus');

  $result = $this->action->execute($payment, $dto);

  expect($result['success'])->toBeFalse()
    ->and($result['status'])->toBe(Payment::STATUS_PAID);
});

test('it returns the correct circuit service name', function () {
  // إنشاء ReflectionClass للوصول للدالة المحمية
  $reflection = new \ReflectionClass($this->action);
  $method = $reflection->getMethod('circuitServiceName');
  $method->setAccessible(true);

  // استدعاء الدالة
  $result = $method->invoke($this->action);

  expect($result)->toBe('Payment-service');
});
