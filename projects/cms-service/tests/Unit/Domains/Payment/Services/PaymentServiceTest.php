<?php

namespace Tests\Unit\Domains\Payment\Services;

use App\Domains\Payment\Actions\BuildRefundContextAction;
use App\Domains\Payment\Actions\FindPaymentAction;
use App\Domains\Payment\Actions\PayInstallmentAction;
use App\Domains\Payment\Actions\ProcessPaymentAction;
use App\Domains\Payment\Actions\RefundViaGatewayAction;
use App\Domains\Payment\Actions\RefundViaWalletAction;
use App\Domains\Payment\Actions\TopUpWalletAction;
use App\Domains\Payment\Actions\ValidateRefundAction;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use App\Domains\Payment\DTOs\TopUpDTO;
use App\Domains\Payment\Services\PaymentService;
use App\Models\Payment;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Mockery;

beforeEach(function () {
  // 1. تجهيز الـ Mocks لجميع الـ Actions المحقونة
  $this->processAction = Mockery::mock(ProcessPaymentAction::class);
  $this->installmentAction = Mockery::mock(PayInstallmentAction::class);
  $this->topUpAction = Mockery::mock(TopUpWalletAction::class);
  $this->findPayment = Mockery::mock(FindPaymentAction::class);
  $this->validateRefund = Mockery::mock(ValidateRefundAction::class);
  $this->buildContext = Mockery::mock(BuildRefundContextAction::class);
  $this->refundViaGateway = Mockery::mock(RefundViaGatewayAction::class);
  $this->refundViaWallet = Mockery::mock(RefundViaWalletAction::class);

  // 2. حقن الـ Mocks داخل الـ Service
  $this->service = new PaymentService(
    $this->processAction,
    $this->installmentAction,
    $this->topUpAction,
    $this->findPayment,
    $this->validateRefund,
    $this->buildContext,
    $this->refundViaGateway,
    $this->refundViaWallet
  );
});

afterEach(function () {
  Mockery::close();
});

// ─── الاختبارات المباشرة (تفويض مباشر للـ Actions) ────────────────────────────────

test('it delegates process payment correctly', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'John',
    'project_id' => 10,
    'amount' => 100,
    'currency' => 'USD',
    'gateway' => 'stripe'
  ]);

  $this->processAction->shouldReceive('execute')
    ->once()->with($dto)->andReturn(['status' => 'success']);

  $result = $this->service->processPayment($dto);
  expect($result)->toBe(['status' => 'success']);
});

test('it delegates pay installment correctly', function () {
  $dto = new PayInstallmentDTO(paymentId: 1, gateway: 'stripe', currency: 'USD');

  $this->installmentAction->shouldReceive('execute')
    ->once()->with($dto)->andReturn(['status' => 'installment_paid']);

  $result = $this->service->payInstallment($dto);
  expect($result)->toBe(['status' => 'installment_paid']);
});

test('it delegates top up correctly', function () {
  $dto = new TopUpDTO(wallet: new Wallet(), amount: 50, note: 'test');

  $this->topUpAction->shouldReceive('execute')
    ->once()->with($dto)->andReturn(['status' => 'wallet_topped_up']);

  $result = $this->service->topUp($dto);
  expect($result)->toBe(['status' => 'wallet_topped_up']);
});

// ─── اختبارات دالة Process Refund (التأكد من مسارات المعاملة والشروط) ──────────────

test('it processes refund via GATEWAY correctly', function () {
  $dto = RefundDTO::fromArray(['payment_id' => 99, 'gateway' => 'stripe', 'amount' => 50]);
  $updatedDto = RefundDTO::fromArray(['payment_id' => 99, 'gateway' => 'stripe', 'amount' => 50, 'reason' => 'context_added']);

  // محاكاة موديل Payment بأن البوابة ليست المحفظة
  $paymentMock = Mockery::mock(Payment::class)->makePartial();
  $paymentMock->gateway = 'stripe';

  $this->findPayment->shouldReceive('execute')->once()->with(99)->andReturn($paymentMock);
  $this->validateRefund->shouldReceive('execute')->once()->with($paymentMock, $dto);
  $this->buildContext->shouldReceive('execute')->once()->with($paymentMock, $dto)->andReturn($updatedDto);

  // يجب أن ينادي Gateway وليس Wallet
  $this->refundViaGateway->shouldReceive('execute')->once()->with($paymentMock, $updatedDto)->andReturn(['refunded' => true]);
  $this->refundViaWallet->shouldNotReceive('execute');

  $result = $this->service->processRefund($dto);

  expect($result)->toBe(['refunded' => true]);
});

test('it processes refund via WALLET correctly', function () {
  $dto = RefundDTO::fromArray(['payment_id' => 55, 'gateway' => 'wallet', 'amount' => 20]);
  $updatedDto = clone $dto;

  // محاكاة موديل Payment بأن البوابة هي المحفظة
  $paymentMock = Mockery::mock(Payment::class)->makePartial();
  $paymentMock->gateway = 'wallet';

  $this->findPayment->shouldReceive('execute')->once()->with(55)->andReturn($paymentMock);
  $this->validateRefund->shouldReceive('execute')->once()->with($paymentMock, $dto);
  $this->buildContext->shouldReceive('execute')->once()->with($paymentMock, $dto)->andReturn($updatedDto);

  // يجب أن ينادي Wallet وليس Gateway بسبب الشرط الموجود في الكود
  $this->refundViaWallet->shouldReceive('execute')->once()->with($paymentMock, $updatedDto)->andReturn(['wallet_refunded' => true]);
  $this->refundViaGateway->shouldNotReceive('execute');

  $result = $this->service->processRefund($dto);

  expect($result)->toBe(['wallet_refunded' => true]);
});
