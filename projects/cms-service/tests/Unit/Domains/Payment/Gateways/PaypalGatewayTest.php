<?php

namespace Tests\Unit\Domains\Payment\Gateways;

use App\Domains\Payment\Gateways\PaypalGateway;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use Braintree\Gateway;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. تعيين إعدادات وهمية في الـ Config تمنع دالة البناء من الانهيار
  config(['payment.gateways.paypal.environment' => 'sandbox']);
  config(['payment.gateways.paypal.merchant_id' => 'fake_merchant_id']);
  config(['payment.gateways.paypal.public_key' => 'fake_public_key']);
  config(['payment.gateways.paypal.private_key' => 'fake_private_key']);

  // 2. إنشاء نسخة من البوابة البرمجية
  $this->paypalGateway = new PaypalGateway();

  // 3. إنشاء الـ Mocks للتحكم بـ Braintree SDK داخلياً
  $this->braintreeGatewayMock = Mockery::mock(Gateway::class);
  $this->transactionGatewayMock = Mockery::mock();

  // ربط ميثود transaction() لتعيد الـ Transaction Gateway الخاص بنا
  $this->braintreeGatewayMock->shouldReceive('transaction')->andReturn($this->transactionGatewayMock);

  // 4. استخدام الـ Reflection لحقن الـ Mock داخل الخاصية المغلقة (private $gateway)
  $reflection = new \ReflectionClass($this->paypalGateway);
  $property = $reflection->getProperty('gateway');
  $property->setAccessible(true);
  $property->setValue($this->paypalGateway, $this->braintreeGatewayMock);
});

// ─── اختبارات عمليات الشحن والدفع (Charge) ───────────────────────────────

test('it processes full charge successfully via braintree', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'Ahmed Ali',
    'project_id' => 10,
    'amount' => 150.00,
    'currency' => 'USD',
    'gateway' => 'paypal',
  ]);

  // تجهيز الرد الناجح المتوقع من Braintree SDK
  $fakeTransaction = (object) ['id' => 'bt_trans_999', 'status' => 'submitted_for_settlement'];
  $fakeResult = (object) ['success' => true, 'transaction' => $fakeTransaction];

  $this->transactionGatewayMock->shouldReceive('sale')
    ->once()
    ->with(Mockery::on(function ($argument) {
      return $argument['amount'] === '150.00' && $argument['customer']['firstName'] === 'Ahmed Ali';
    }))
    ->andReturn($fakeResult);

  $result = $this->paypalGateway->charge($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['transaction_id'])->toBe('bt_trans_999')
    ->and($result['amount'])->toBe(150.00)
    ->and($result['status'])->toBe('submitted_for_settlement');
});

test('it handles failed charge response from braintree gracefully', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'Ahmed Ali',
    'project_id' => 10,
    'amount' => 50.00,
    'currency' => 'USD',
    'gateway' => 'paypal',
  ]);

  $fakeResult = (object) ['success' => false, 'message' => 'Insufficent funds'];

  $this->transactionGatewayMock->shouldReceive('sale')->once()->andReturn($fakeResult);

  // توقع كتابة سجل تحذيري في الـ Logs
  Log::shouldReceive('warning')->once();

  $result = $this->paypalGateway->chargeAmount($dto, 50.00);

  expect($result['success'])->toBeFalse()
    ->and($result['status'])->toBe('failed')
    ->and($result['raw']['error'])->toBe('Insufficent funds');
});

test('it handles exceptions during charge process', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'Ahmed Ali',
    'project_id' => 10,
    'amount' => 50.00,
    'currency' => 'USD',
    'gateway' => 'paypal',
  ]);

  // إجبار الميثود على رمي Exception لمحاكاة انقطاع الاتصال بالسيرفر
  $this->transactionGatewayMock->shouldReceive('sale')
    ->once()
    ->andThrow(new \Exception('Braintree API Timeout'));

  Log::shouldReceive('error')->once();

  $result = $this->paypalGateway->chargeAmount($dto, 50.00);

  expect($result['success'])->toBeFalse()
    ->and($result['raw']['error'])->toBe('Braintree API Timeout');
});

// ─── اختبارات عمليات الاسترداد (Refund) ──────────────────────────────────

test('it executes refund successfully via braintree', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 5,
    'gateway' => 'paypal',
    'amount' => 60.00,
    'transaction_id' => 'bt_trans_999'
  ]);

  $fakeTransaction = (object) ['id' => 'bt_refund_111', 'status' => 'refunded'];
  $fakeResult = (object) ['success' => true, 'transaction' => $fakeTransaction];

  $this->transactionGatewayMock->shouldReceive('refund')
    ->once()
    ->with('bt_trans_999', '60.00')
    ->andReturn($fakeResult);

  $result = $this->paypalGateway->refund($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['refund_id'])->toBe('bt_refund_111')
    ->and($result['status'])->toBe('refunded');
});

test('it handles failed refund response from braintree', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 5,
    'gateway' => 'paypal',
    'amount' => 60.00,
    'transaction_id' => 'bt_trans_999'
  ]);

  $fakeResult = (object) ['success' => false, 'message' => 'Past settlement window'];

  $this->transactionGatewayMock->shouldReceive('refund')->once()->andReturn($fakeResult);

  $result = $this->paypalGateway->refund($dto);

  expect($result['success'])->toBeFalse()
    ->and($result['status'])->toBe('failed')
    ->and($result['raw']['error'])->toBe('Past settlement window');
});

// ─── اختبارات جلب الحالة والرصيد (Status & Balance) ──────────────────────

test('it fetches transaction status from braintree correctly', function () {
  $fakeTransaction = (object) ['status' => 'settled'];

  $this->transactionGatewayMock->shouldReceive('find')
    ->once()
    ->with('bt_trans_999')
    ->andReturn($fakeTransaction);

  $result = $this->paypalGateway->status('bt_trans_999');

  expect($result['status'])->toBe('settled');
});

test('it calculates settled balance correctly from transactions list', function () {
  // محاكاة مصفوفة كائنات راجعة من بحث معاملات Braintree المسواة (Settled)
  $fakeCollection = [
    (object) ['amount' => '100.50'],
    (object) ['amount' => '200.00'],
    (object) ['amount' => '50.25'],
  ];

  $this->transactionGatewayMock->shouldReceive('search')
    ->once()
    ->andReturn($fakeCollection);

  $result = $this->paypalGateway->getBalance();

  expect($result['success'])->toBeTrue()
    // مجموع المبالغ أعلاه: 350.75
    ->and($result['settled_balance_usd'])->toBe('350.75');
});

test('it handles exceptions during refund process', function () {
    $dto = RefundDTO::fromArray([
        'payment_id' => 5,
        'gateway' => 'paypal',
        'amount' => 60.00,
        'transaction_id' => 'bt_trans_999'
    ]);

    // إجبار دالة الـ refund على رمي Exception لمحاكاة فشل الاتصال
    $this->transactionGatewayMock->shouldReceive('refund')
        ->once()
        ->andThrow(new \Exception('Braintree Refund Connection Failed'));

    // توقّع تسجيل الخطأ في الـ Logs
    Log::shouldReceive('error')->once();

    $result = $this->paypalGateway->refund($dto);

    // التأكد من مخرجات الـ catch بدقة
    expect($result['success'])->toBeFalse()
        ->and($result['refund_id'])->toBe('')
        ->and($result['status'])->toBe('failed')
        ->and($result['raw']['error'])->toBe('Braintree Refund Connection Failed');
});

test('it handles exceptions when fetching transaction status', function () {
    // إجبار دالة الـ find على رمي Exception
    $this->transactionGatewayMock->shouldReceive('find')
        ->once()
        ->with('bt_trans_999')
        ->andThrow(new \Exception('Braintree Status API Error'));

    $result = $this->paypalGateway->status('bt_trans_999');

    // التأكد من إرجاع الحالة unknown والرسالة المطلوبة
    expect($result['status'])->toBe('unknown')
        ->and($result['raw']['error'])->toBe('Braintree Status API Error');
});