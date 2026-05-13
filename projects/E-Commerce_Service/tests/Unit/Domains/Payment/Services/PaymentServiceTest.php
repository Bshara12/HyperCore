<?php

namespace Tests\Unit\Domains\Payment\Services;

use App\Domains\Payment\Services\PaymentService;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\PayInstallmentDTO;
use App\Services\CMS\CMSApiClient;
use Mockery;

beforeEach(function () {
  // 1. عمل Mock للـ CMSApiClient
  $this->cmsMock = Mockery::mock(CMSApiClient::class);

  // 2. حقن الـ Mock في الخدمة
  $this->service = new PaymentService($this->cmsMock);
});

afterEach(function () {
  Mockery::close();
});

it('forwards processPayment call to cms api client correctly', function () {
  // إعداد الـ DTO
  $dto = new PaymentDTO(
    userId: 1,
    userName: 'Zaid',
    projectId: 10,
    amount: 100.0,
    currency: 'USD',
    gateway: 'stripe',
    paymentType: 'full'
  );

  $expectedResponse = ['status' => 'success', 'transaction_id' => 'tx_123'];

  // التوقع: يجب استدعاء processPayment داخل الـ Mock مرة واحدة مع الـ DTO الممرر
  $this->cmsMock->shouldReceive('processPayment')
    ->once()
    ->with($dto)
    ->andReturn($expectedResponse);

  $result = $this->service->processPayment($dto);

  expect($result)->toBe($expectedResponse);
});

it('forwards payInstallment call to cms api client correctly', function () {
  // إعداد الـ DTO الخاص بالتقسيط
  $dto = new PayInstallmentDTO(
    payment_id: 50,
    gateway: 'wallet',
    currency: 'EGP',
    to_wallet_number: '01000000000'
  );

  $expectedResponse = ['status' => 'paid', 'installment_id' => 5];

  // التوقع: يجب استدعاء payInstallment داخل الـ Mock مرة واحدة
  $this->cmsMock->shouldReceive('payInstallment')
    ->once()
    ->with($dto)
    ->andReturn($expectedResponse);

  $result = $this->service->payInstallment($dto);

  expect($result)->toBe($expectedResponse);
});
