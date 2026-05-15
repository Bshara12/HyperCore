<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\OrderService;
use App\Http\Controllers\OrderController;
use App\Domains\E_Commerce\Requests\CreateOrderRequest;
use App\Domains\E_Commerce\Requests\UpdateOrderStatusRequest;
use Mockery\MockInterface;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;

class OrderControllerTest extends TestCase
{
  use WithoutMiddleware;

  private MockInterface $orderServiceMock;
  private array $mockUser = ['id' => 1, 'name' => 'Test User'];
  private int $projectId = 10;

  protected function setUp(): void
  {
    parent::setUp();
    $this->orderServiceMock = $this->mock(OrderService::class);
  }

  #[Test]
  public function it_can_list_user_orders()
  {
    $mockOrders = [['id' => 1, 'total' => 100], ['id' => 2, 'total' => 200]];

    $this->orderServiceMock
      ->shouldReceive('listOrders')
      ->once()
      ->with($this->projectId, $this->mockUser['id'])
      ->andReturn($mockOrders);

    $request = Request::create('/api/orders', 'GET', ['project_id' => $this->projectId]);
    $request->attributes->set('auth_user', $this->mockUser);
    $this->app->instance('request', $request);

    $controller = new OrderController($this->orderServiceMock);
    $response = $controller->index($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Orders fetched successfully', 'data' => $mockOrders]);
  }

  #[Test]
  public function it_can_list_admin_orders_with_filters()
  {
    $filters = ['status' => 'pending', 'user_id' => '5'];
    $mockOrders = [['id' => 10, 'status' => 'pending']];

    $this->orderServiceMock
      ->shouldReceive('adminListOrders')
      ->once()
      ->with($this->projectId, $filters)
      ->andReturn($mockOrders);

    $request = Request::create('/api/admin/orders', 'GET', array_merge(['project_id' => $this->projectId], $filters));
    $this->app->instance('request', $request);

    $controller = new OrderController($this->orderServiceMock);
    $response = $controller->adminIndex($request);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Admin orders fetched successfully']);
  }

  #[Test]
  public function it_can_show_order_details()
  {
    $orderId = 50;
    $mockOrder = ['id' => $orderId, 'status' => 'shipped'];

    $this->orderServiceMock
      ->shouldReceive('getOrderDetails')
      ->once()
      ->with($orderId, $this->projectId, $this->mockUser['id'])
      ->andReturn($mockOrder);

    $request = Request::create("/api/orders/{$orderId}", 'GET', ['project_id' => $this->projectId]);
    $request->attributes->set('auth_user', $this->mockUser);
    $this->app->instance('request', $request);

    $controller = new OrderController($this->orderServiceMock);
    $response = $controller->show($request, $orderId);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['data' => $mockOrder]);
  }

  #[Test]
  public function it_can_update_order_status()
  {
    $orderId = 50;
    $newStatus = 'completed';
    $mockOrder = ['id' => $orderId, 'status' => $newStatus];

    $this->orderServiceMock
      ->shouldReceive('updateOrderStatus')
      ->once()
      ->with($orderId, $this->projectId, $newStatus)
      ->andReturn($mockOrder);

    $payload = ['project_id' => $this->projectId, 'status' => $newStatus];
    $request = UpdateOrderStatusRequest::create("/api/orders/{$orderId}/status", 'PUT', $payload);
    $this->app->instance(UpdateOrderStatusRequest::class, $request);

    $controller = new OrderController($this->orderServiceMock);
    $response = $controller->updateStatus($request, $orderId);

    $testResponse = $this->createTestResponse($response, $request);
    $testResponse->assertStatus(200)
      ->assertJson(['message' => 'Order status updated successfully']);
  }

  #[Test]
  public function it_can_store_order_successfully()
  {
    // 1. تجهيز البيانات (Payload) بناءً على القواعد التي أرسلتها في CreateOrderRequest
    $payload = [
      'cart_id' => 5,
      'project_id' => $this->projectId,
      'address' => [
        'full_address' => 'شارع التخصصي، حي العليا، الرياض',
        'city' => 'Riyadh',
        'street' => 'Takhassusi St',
        'latitude' => 24.7136,
        'longitude' => 46.6753,
        'phone' => '0501234567'
      ]
    ];

    // 2. البيانات المتوقع عودتها من الخدمة بعد إنشاء الطلب
    $mockOrder = [
      'id' => 101,
      'project_id' => $this->projectId,
      'user_id' => $this->mockUser['id'],
      'status' => 'pending',
      'total_price' => 250.00
    ];

    // 3. توقع استدعاء الخدمة (createFromCart)
    $this->orderServiceMock
      ->shouldReceive('createFromCart')
      ->once()
      ->andReturn($mockOrder);

    // 4. بناء الـ Request وحقن المستخدم في الـ attributes
    $request = \App\Domains\E_Commerce\Requests\CreateOrderRequest::create(
      '/api/ecommerce/orders',
      'POST',
      $payload
    );
    $request->attributes->set('auth_user', $this->mockUser);

    // حقن الـ Request في الحاوية لضمان عمل الـ DTO
    $this->app->instance(\App\Domains\E_Commerce\Requests\CreateOrderRequest::class, $request);
    $this->app->instance('request', $request);

    // 5. استدعاء التابع من الـ Controller
    $controller = new OrderController($this->orderServiceMock);
    $response = $controller->store($request);

    // 6. التحقق من النتيجة النهائية
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Order created successfully',
        'data' => [
          'id' => 101,
          'status' => 'pending'
        ]
      ]);
  }
}
