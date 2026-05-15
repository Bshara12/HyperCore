<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\Payment\Services\PaymentService;
use App\Http\Controllers\PaymentController;
use App\Domains\Payment\Requests\ProcessPaymentRequest;
use App\Domains\Payment\Requests\PayInstallmentRequest;
use App\Models\Wallet;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class PaymentControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $paymentServiceMock;
  private array $mockUser = ['id' => 1, 'name' => 'Ahmed'];

  protected function setUp(): void
  {
    parent::setUp();
    $this->paymentServiceMock = $this->mock(PaymentService::class);
  }

  // --- اختبارات Charge (دفع كاش أو تقسيط) ---

  #[Test]
  public function it_charges_successfully_for_one_time_payment()
  {
    $payload = [
      'project_id' => 10,
      'amount' => 1000,
      'currency' => 'usd',
      'gateway' => 'stripe',
      'payment_type' => 'one_time'
    ];

    $mockResult = ['status' => 'paid', 'transaction_id' => 'TXN_123'];

    $this->paymentServiceMock
      ->shouldReceive('processPayment')
      ->once()
      ->andReturn($mockResult);

    $request = ProcessPaymentRequest::create('/api/payments', 'POST', $payload);
    $request->attributes->set('auth_user', $this->mockUser);
    $this->app->instance(ProcessPaymentRequest::class, $request);

    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->charge($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201)->assertJson($mockResult);
  }

  #[Test]
  public function it_returns_error_if_payment_status_is_unexpected()
  {
    $payload = [
      'project_id' => 10,
      'amount' => 1000,
      'currency' => 'usd',
      'gateway' => 'stripe',
      'payment_type' => 'one_time'
    ];

    // الحالة المتوقعة paid، لكن سنعيد failed
    $mockResult = ['status' => 'failed'];

    $this->paymentServiceMock
      ->shouldReceive('processPayment')
      ->once()
      ->andReturn($mockResult);

    $request = ProcessPaymentRequest::create('/api/payments', 'POST', $payload);
    $request->attributes->set('auth_user', $this->mockUser);
    $this->app->instance(ProcessPaymentRequest::class, $request);

    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->charge($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(422)
      ->assertJson(['message' => 'Payment failed. Please try again.']);
  }

  #[Test]
  public function it_catches_exceptions_in_charge()
  {
    // يجب إرسال البيانات المطلوبة للـ DTO لتجنب TypeError قبل الوصول للـ Service
    $payload = [
      'project_id' => 10,
      'amount' => 500,
      'currency' => 'USD',
      'gateway' => 'stripe',
      'payment_type' => 'one_time'
    ];

    $request = ProcessPaymentRequest::create('/api/payments', 'POST', $payload);
    $request->attributes->set('auth_user', $this->mockUser);

    // هنا نجعل الخدمة ترمي الخطأ يدوياً لاختبار الـ catch في الـ Controller
    $this->paymentServiceMock
      ->shouldReceive('processPayment')
      ->once()
      ->andThrow(new \Exception("Gateway Timeout"));

    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->charge($request);

    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(422)
      ->assertJson(['message' => 'Gateway Timeout']);
  }

  // --- اختبارات PayInstallment (دفع قسط محدد) ---

  #[Test]
  public function it_pays_installment_successfully()
  {
    $payload = [
      'payment_id' => 5,
      'gateway' => 'paypal',
      'currency' => 'SAR'
    ];

    $mockResult = ['message' => 'Installment paid successfully.', 'status' => 'paid'];

    $this->paymentServiceMock
      ->shouldReceive('payInstallment')
      ->once()
      ->andReturn($mockResult);

    $request = PayInstallmentRequest::create('/api/payments/installment', 'POST', $payload);
    $this->app->instance(PayInstallmentRequest::class, $request);

    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->payInstallment($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(201)->assertJson($mockResult);
  }

  #[Test]
  public function it_fails_if_installment_message_is_not_success()
  {
    $payload = ['payment_id' => 5, 'gateway' => 'paypal', 'currency' => 'SAR'];
    $mockResult = ['message' => 'Insufficient funds', 'status' => 'failed'];

    $this->paymentServiceMock
      ->shouldReceive('payInstallment')
      ->once()
      ->andReturn($mockResult);

    $request = PayInstallmentRequest::create('/api/payments/installment', 'POST', $payload);

    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->payInstallment($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(422)
      ->assertJson(['message' => 'Installment payment failed.']);
  }

  #[Test]
  public function it_catches_exceptions_in_pay_installment()
  {
    // 1. تجهيز البيانات
    $payload = [
      'payment_id' => 5,
      'gateway' => 'paypal',
      'currency' => 'SAR'
    ];

    $request = PayInstallmentRequest::create('/api/payments/installment', 'POST', $payload);
    $this->app->instance(PayInstallmentRequest::class, $request);

    // 2. جعل الخدمة ترمي استثناء
    $this->paymentServiceMock
      ->shouldReceive('payInstallment')
      ->once()
      ->andThrow(new \Exception("Database connection error"));

    // 3. التنفيذ
    $controller = new PaymentController($this->paymentServiceMock);
    $response = $controller->payInstallment($request);

    // 4. التحقق
    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(422)
      ->assertJson(['message' => 'Database connection error']);
  }
}
