<?php

namespace Tests\Unit\Domains\Payment\Gateways;

use App\Domains\Payment\Gateways\StripeGateway;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\DTOs\RefundDTO;
use Illuminate\Support\Facades\Log;
use Mockery;

beforeEach(function () {
  // تعيين مفتاح تجريبي لمنع انهيار دالة البناء
  config(['payment.gateways.stripe.secret_key' => 'sk_test_mock_key']);
  $this->gateway = new StripeGateway();
});

afterEach(function () {
  Mockery::close();
});

// ─── اختبارات عمليات الدفع والشحن (Charge) ───────────────────────────────

test('it charges full amount successfully via stripe', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'project_id' => 45,
    'amount' => 100.00,
    'currency' => 'USD',
    'gateway' => 'stripe',
  ]);

  // محاكاة كائن كلاس الـ Charge الناجح الراجع من Stripe
  $fakeCharge = new class {
    public $status = 'succeeded';
    public $id = 'ch_success_123';
    public function toArray()
    {
      return ['id' => 'ch_success_123', 'status' => 'succeeded'];
    }
  };

  // اعتراض الدالة الإستاتيكية Charge::create
  Mockery::mock('alias:Stripe\Charge')
    ->shouldReceive('create')
    ->once()
    ->andReturn($fakeCharge);

  $result = $this->gateway->charge($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['transaction_id'])->toBe('ch_success_123')
    ->and($result['status'])->toBe('succeeded');
});

test('it handles exceptions during stripe charge process', function () {
  $dto = PaymentDTO::fromArray([
    'user_id' => 1,
    'user_name' => 'John Doe',
    'project_id' => 45,
    'amount' => 100.00,
    'currency' => 'USD',
    'gateway' => 'stripe',
  ]);

  // إجبار دالة الإنشاء على رمي استثناء لتغطية بلوك الـ catch لـ chargeAmount
  Mockery::mock('alias:Stripe\Charge')
    ->shouldReceive('create')
    ->once()
    ->andThrow(new \Exception('Stripe Card Declined'));

  Log::shouldReceive('error')->once();

  $result = $this->gateway->charge($dto);

  expect($result['success'])->toBeFalse()
    ->and($result['status'])->toBe('failed')
    ->and($result['raw']['error'])->toBe('Stripe Card Declined');
});

// ─── اختبارات عمليات الاسترداد وتغطية دالة الخريطة (Refund & Map Reason) ───

test('it processes refund with duplicate reason successfully', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 1,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
    'transaction_id' => 'ch_123',
    'reason' => 'This is a duplicate payment error', // ستتحول إلى duplicate
  ]);

  $fakeRefund = new class {
    public $status = 'succeeded';
    public $id = 're_123';
    public function toArray()
    {
      return ['id' => 're_123', 'status' => 'succeeded'];
    }
  };

  Mockery::mock('alias:Stripe\Refund')
    ->shouldReceive('create')
    ->once()
    ->with(Mockery::on(function ($args) {
      return $args['reason'] === 'duplicate'; // التأكد من نجاح الـ mapping للسبب
    }))
    ->andReturn($fakeRefund);

  $result = $this->gateway->refund($dto);

  expect($result['success'])->toBeTrue()
    ->and($result['refund_id'])->toBe('re_123');
});

test('it processes refund with fraudulent reason successfully', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 1,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
    'transaction_id' => 'ch_123',
    'reason' => 'fraudulent activity suspected', // ستتحول إلى fraudulent
  ]);

  $fakeRefund = new class {
    public $status = 'succeeded';
    public $id = 're_456';
    public function toArray()
    {
      return ['id' => 're_456', 'status' => 'succeeded'];
    }
  };

  Mockery::mock('alias:Stripe\Refund')
    ->shouldReceive('create')
    ->once()
    ->with(Mockery::on(function ($args) {
      return $args['reason'] === 'fraudulent';
    }))
    ->andReturn($fakeRefund);

  $result = $this->gateway->refund($dto);

  expect($result['success'])->toBeTrue();
});

test('it processes refund with default customer requested reason', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 1,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'currency' => 'USD',
    'transaction_id' => 'ch_123',
    'reason' => 'Customer changed their mind', // ستتحول إلى requested_by_customer
  ]);

  $fakeRefund = new class {
    public $status = 'succeeded';
    public $id = 're_789';
    public function toArray()
    {
      return ['id' => 're_789', 'status' => 'succeeded'];
    }
  };

  Mockery::mock('alias:Stripe\Refund')
    ->shouldReceive('create')
    ->once()
    ->with(Mockery::on(function ($args) {
      return $args['reason'] === 'requested_by_customer';
    }))
    ->andReturn($fakeRefund);

  $result = $this->gateway->refund($dto);

  expect($result['success'])->toBeTrue();
});

test('it handles exceptions during stripe refund process', function () {
  $dto = RefundDTO::fromArray([
    'payment_id' => 1,
    'gateway' => 'stripe',
    'amount' => 50.00,
    'transaction_id' => 'ch_123',
  ]);

  // إجبار الـ Refund على الفشل لتغطية الـ catch الخاصة بالـ refund
  Mockery::mock('alias:Stripe\Refund')
    ->shouldReceive('create')
    ->once()
    ->andThrow(new \Exception('Stripe Refund API Error'));

  Log::shouldReceive('error')->once();

  $result = $this->gateway->refund($dto);

  expect($result['success'])->toBeFalse()
    ->and($result['raw']['error'])->toBe('Stripe Refund API Error');
});

// ─── اختبارات جلب الحالة والرصيد (Status & Balance) ──────────────────────

test('it retrieves charge status successfully', function () {
  $fakeCharge = new class {
    public $status = 'succeeded';
    public function toArray()
    {
      return ['status' => 'succeeded'];
    }
  };

  Mockery::mock('alias:Stripe\Charge')
    ->shouldReceive('retrieve')
    ->once()
    ->with('ch_123')
    ->andReturn($fakeCharge);

  $result = $this->gateway->status('ch_123');

  expect($result['status'])->toBe('succeeded');
});

test('it handles exceptions when retrieving charge status', function () {
  // إجبار الـ retrieve على الفشل لتغطية الـ catch الخاصة بالـ status
  Mockery::mock('alias:Stripe\Charge')
    ->shouldReceive('retrieve')
    ->once()
    ->with('ch_123')
    ->andThrow(new \Exception('Charge not found'));

  $result = $this->gateway->status('ch_123');

  expect($result['status'])->toBe('unknown')
    ->and($result['raw']['error'])->toBe('Charge not found');
});

test('it retrieves stripe available balance successfully', function () {
  $fakeBalance = new class {
    public $available;
    public function __construct()
    {
      $this->available = [
        (object) ['amount' => 15000]
      ];
    }
  };

  Mockery::mock('alias:Stripe\Balance')
    ->shouldReceive('retrieve')
    ->once()
    ->andReturn($fakeBalance);

  $result = $this->gateway->getBalance();

  expect($result['success'])->toBeTrue()
    ->and($result['balance'])->toBe(150); // التعديل هنا: 150 كـ Integer ليطابق الناتج تماماً
});
