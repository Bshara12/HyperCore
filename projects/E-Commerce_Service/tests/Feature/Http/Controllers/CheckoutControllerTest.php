<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Actions\Order\CheckoutAction;
use App\Domains\E_Commerce\Requests\CheckoutRequest;
use App\Http\Controllers\CheckoutController;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class CheckoutControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $checkoutActionMock;

  protected function setUp(): void
  {
    parent::setUp();
    // عمل Mock للـ Action
    $this->checkoutActionMock = $this->mock(CheckoutAction::class);
  }

  #[Test]
  public function it_can_complete_checkout_successfully()
  {
    // 1. بيانات المستخدم (لاحظ وجود id و name كما يتوقع الـ DTO)
    $mockUser = [
      'id' => 1,
      'name' => 'Gemini Developer'
    ];

    // 2. البيانات المرسلة (Payload)
    $payload = [
      'project_id' => 2,
      'cart_id' => 10,
      'payment_method' => 'stripe',
      'gateway' => 'card',
      'payment_type' => 'instant',
      'address' => [
        'city' => 'Riyadh',
        'street' => 'King Fahd Road',
        'zip' => '12345'
      ]
    ];

    // 3. البيانات المتوقع عودتها (الطلب الذي تم إنشاؤه)
    $mockOrderResponse = [
      'id' => 500,
      'user_id' => 1,
      'total_price' => 150.00,
      'status' => 'pending',
      'payment_status' => 'unpaid'
    ];

    // 4. توقع تنفيذ الـ Action (execute)
    $this->checkoutActionMock
      ->shouldReceive('execute')
      ->once()
      ->andReturn($mockOrderResponse);

    // 5. بناء الـ Request وحقن الـ attributes يدوياً
    $request = CheckoutRequest::create('/api/ecommerce/checkout', 'POST', $payload);
    $request->attributes->set('auth_user', $mockUser);

    // إخبار الحاوية باستخدام هذه النسخة
    $this->app->instance(CheckoutRequest::class, $request);
    $this->app->instance('request', $request);

    // 6. استدعاء الـ Controller مباشرة
    $controller = new CheckoutController($this->checkoutActionMock);
    $response = $controller->store($request);

    // 7. التحقق من النتيجة
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Checkout completed successfully',
        'data' => [
          'id' => 500,
          'status' => 'pending'
        ]
      ]);
  }
}
