<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Http\Controllers\PaymentController;
use App\Domains\Payment\Services\PaymentService;
use App\Domains\Payment\DTOs\PaymentDTO;
use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Domains\Payment\Requests\RefundRequest;
use App\Domains\Payment\Requests\TopUpWalletRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use App\Models\Wallet;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class PaymentControllerTest extends TestCase
{
  use RefreshDatabase;

  private PaymentController $controller;
  private $paymentServiceMock;

  protected function setUp(): void
  {
    parent::setUp();

    $this->paymentServiceMock = Mockery::mock(PaymentService::class);
    $this->controller = new PaymentController($this->paymentServiceMock);
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }

  // ==========================================
  // اختبارات التابع CHARGE
  // ==========================================

  #[Test]
  public function it_charges_payment_successfully()
  {
    $payload = [
      'userId' => 1,
      'userName' => 'Test User',
      'projectId' => 10,
      'amount' => 200.50,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'paymentType' => 'full'
    ];

    $request = Request::create('/payments/pay', 'POST', $payload);

    $mockResult = [
      'success' => true,
      'payment_id' => 'PAY-12345',
      'transaction_id' => 'TXN-999',
      'payment_method' => 'stripe',
      'status' => 'completed',
      'installment_number' => null
    ];

    $this->paymentServiceMock->shouldReceive('processPayment')
      ->once()
      ->with(Mockery::type(PaymentDTO::class))
      ->andReturn($mockResult);

    $response = $this->controller->charge($request);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertStringContainsString('Payment processed successfully.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_payment_fails()
  {
    $payload = [
      'userId' => 1,
      'userName' => 'Test User',
      'projectId' => 10,
      'amount' => 200.50,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'paymentType' => 'full'
    ];

    $request = Request::create('/payments/pay', 'POST', $payload);

    $this->paymentServiceMock->shouldReceive('processPayment')
      ->once()
      ->andReturn([
        'success' => false,
        'status' => 'declined'
      ]);

    $response = $this->controller->charge($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Payment failed.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_exception_is_thrown()
  {
    $payload = [
      'userId' => 1,
      'userName' => 'Test User',
      'projectId' => 10,
      'amount' => 100.00,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'paymentType' => 'full'
    ];

    $request = Request::create('/payments/pay', 'POST', $payload);

    $this->paymentServiceMock->shouldReceive('processPayment')
      ->once()
      ->andThrow(new \Exception('Gateway Connection Error'));

    $response = $this->controller->charge($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Gateway Connection Error', $response->getContent());
  }

  // ==========================================
  // اختبارات التابع PAY INSTALLMENT المحدثة للتغطية الكاملة
  // ==========================================

  #[Test]
  public function it_pays_installment_successfully()
  {
    Wallet::create([
      'user_id' => 1,
      'wallet_number' => 'W-12345',
      'balance' => 1000.00
    ]);

    $payload = [
      'payment_id' => 5,
      'gateway' => 'wallet',
      'currency' => 'USD',
      'toWallet' => 'W-12345',
      'to_wallet_number' => 'W-12345'
    ];

    // استخدام Request حقيقي لضمان التقاط الأداة للأسطر الداخلية
    $request = PayInstallmentRequest::create('/payments/pay-installment', 'POST', $payload);

    $this->paymentServiceMock->shouldReceive('payInstallment')
      ->once()
      ->andReturn([
        'success' => true,
        'payment_id' => 'PAY-555',
        'transaction_id' => 'TXN-888',
        'payment_method' => 'wallet',
        'installment_number' => 1,
        'remaining' => 100.00,
        'plan_status' => 'active'
      ]);

    $response = $this->controller->payInstallment($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Installment paid successfully.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_if_installment_payment_fails()
  {
    Wallet::create([
      'user_id' => 2,
      'wallet_number' => 'W-67890',
      'balance' => 0.00
    ]);

    $payload = [
      'payment_id' => 5,
      'gateway' => 'wallet',
      'currency' => 'USD',
      'toWallet' => 'W-67890',
      'to_wallet_number' => 'W-67890'
    ];

    $request = PayInstallmentRequest::create('/payments/pay-installment', 'POST', $payload);

    $this->paymentServiceMock->shouldReceive('payInstallment')
      ->once()
      ->andReturn(['success' => false, 'status' => 'insufficient_funds']);

    $response = $this->controller->payInstallment($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Installment payment failed.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_pay_installment_throws_exception()
  {
    // 🔥 تم الإصلاح: يجب إنشاء المحفظة أولاً لكي يتخطاها الـ DTO بنجاح ويصل إلى الـ Service
    Wallet::create([
      'user_id' => 4,
      'wallet_number' => 'W-12345',
      'balance' => 1000.00
    ]);

    $payload = [
      'payment_id' => 5,
      'gateway' => 'wallet',
      'currency' => 'USD',
      'toWallet' => 'W-12345',
      'to_wallet_number' => 'W-12345'
    ];

    $request = PayInstallmentRequest::create('/payments/installment', 'POST', $payload);

    // الآن سيصل الطلب إلى هنا بنجاح ويرمي الخطأ المطلوب اختباره
    $this->paymentServiceMock->shouldReceive('payInstallment')
      ->once()
      ->andThrow(new \Exception('Database connection lost'));

    $response = $this->controller->payInstallment($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Database connection lost', $response->getContent());
  }
  // ==========================================
  // اختبارات التابع TOP UP
  // ==========================================

  #[Test]
  public function it_tops_up_wallet_successfully()
  {
    // 🔥 تم الإصلاح: تمرير user_id لتفادي قيود الـ Database
    Wallet::create([
      'user_id' => 3,
      'wallet_number' => 'W-11111',
      'balance' => 1000.00
    ]);

    $request = Mockery::mock(TopUpWalletRequest::class);
    $request->wallet_number = 'W-11111';
    $request->toWallet = 'W-11111';
    $request->amount = 500.00;
    $request->note = 'Initial top-up';
    $request->shouldIgnoreMissing();

    $this->paymentServiceMock->shouldReceive('topUp')
      ->once()
      ->andReturn([
        'wallet_id' => 1,
        'amount_added' => 500.00,
        'new_balance' => 1500.00,
        'transaction_id' => 'TXN-777'
      ]);

    $response = $this->controller->topUp($request);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertStringContainsString('Wallet topped up successfully.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_topup_exception_thrown()
  {
    $request = Mockery::mock(TopUpWalletRequest::class);
    $request->wallet_number = 'INVALID';
    $request->toWallet = 'INVALID';
    $request->shouldIgnoreMissing();

    $response = $this->controller->topUp($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Wallet not found', $response->getContent());
  }

  // ==========================================
  // اختبارات التابع REFUND
  // ==========================================

  #[Test]
  public function it_refunds_successfully()
  {
    $request = Mockery::mock(RefundRequest::class);
    $request->payment_id = 10;
    $request->amount = 50.00;
    $request->reason = 'Client request';
    $request->shouldIgnoreMissing();

    $this->paymentServiceMock->shouldReceive('processRefund')
      ->once()
      ->andReturn([
        'success' => true,
        'payment_id' => 10,
        'refund_id' => 'REF-999',
        'payment_method' => 'stripe',
        'status' => 'refunded'
      ]);

    $response = $this->controller->refund($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertStringContainsString('Refund processed successfully.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_refund_is_declined()
  {
    $request = Mockery::mock(RefundRequest::class);
    $request->shouldIgnoreMissing();

    $this->paymentServiceMock->shouldReceive('processRefund')
      ->once()
      ->andReturn(['success' => false, 'status' => 'cannot_refund']);

    $response = $this->controller->refund($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Refund failed.', $response->getContent());
  }

  #[Test]
  public function it_returns_422_when_refund_throws_exception()
  {
    $request = Mockery::mock(RefundRequest::class);
    $request->payment_id = 1;
    $request->amount = 100.00;
    $request->shouldIgnoreMissing();

    $this->paymentServiceMock->shouldReceive('processRefund')
      ->once()
      ->andThrow(new \Exception('Critical service failure'));

    $response = $this->controller->refund($request);

    $this->assertEquals(422, $response->getStatusCode());
    $this->assertStringContainsString('Critical service failure', $response->getContent());
  }
}
