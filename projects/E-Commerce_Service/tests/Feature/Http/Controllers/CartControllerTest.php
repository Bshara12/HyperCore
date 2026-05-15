<?php

namespace Tests\Feature\Http\Controllers;

use Tests\TestCase;
use App\Domains\E_Commerce\Services\CartService;
use App\Domains\E_Commerce\Requests\CreateCartRequest;
use App\Http\Controllers\CartController;
use Mockery\MockInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Testing\TestResponse;

class CartControllerTest extends TestCase
{
  use RefreshDatabase, WithoutMiddleware;

  private MockInterface $cartServiceMock;

  protected function setUp(): void
  {
    parent::setUp();
    $this->cartServiceMock = $this->mock(CartService::class);
  }

  #[Test]
  public function it_can_store_items_in_cart_successfully()
  {
    // 1. البيانات (Payload) ومعرف المستخدم
    $mockUser = ['id' => 1];
    $payload = [
      'project_id' => 2,
      'items' => [
        ['item_id' => 2, 'quantity' => 5],
        ['item_id' => 3, 'quantity' => 2],
        ['item_id' => 1, 'quantity' => 1],
      ]
    ];

    // 2. البيانات المتوقع عودتها من الـ Service
    $mockCartResponse = [
      "id" => 2,
      "project_id" => 2,
      "user_id" => 1,
      "notes" => null,
      "items" => [
        ["item_id" => 2, "quantity" => 5, "price" => "100.00", "subtotal" => "500.00"],
        ["item_id" => 3, "quantity" => 2, "price" => "100.00", "subtotal" => "200.00"],
        ["item_id" => 1, "quantity" => 1, "price" => "100.00", "subtotal" => "100.00"]
      ]
    ];

    // 3. توقع استدعاء الـ Service وإرجاع البيانات
    $this->cartServiceMock
      ->shouldReceive('addItems')
      ->once()
      ->andReturn($mockCartResponse);

    // 4. بناء الـ Request وحقن الـ attributes يدوياً (هذا يضمن عدم وجود Null)
    $request = CreateCartRequest::create('/api/ecommerce/cart', 'POST', $payload);
    $request->attributes->set('auth_user', $mockUser);

    // إخبار الحاوية باستخدام هذا الـ Request في حال استدعاء request() helper
    $this->app->instance('request', $request);

    // 5. استدعاء التابع مباشرة من الـ Controller
    $controller = new CartController($this->cartServiceMock);
    $response = $controller->store($request);

    // 6. التحقق من النتيجة (تم إصلاح استدعاء createTestResponse بتمرير الـ request)
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Cart created successfully',
        'data' => [
          'id' => 2,
          'user_id' => 1,
          'project_id' => 2
        ]
      ]);
  }
  #[Test]
  public function it_can_fetch_cart_successfully()
  {
    // 1. تجهيز البيانات المدخلة (نحتاج project_id ومعرف المستخدم)
    $projectId = 2;
    $mockUser = ['id' => 1];

    // 2. البيانات المتوقع عودتها من الـ Service (عربة التسوق)
    $mockCartData = [
      "id" => 10,
      "project_id" => $projectId,
      "user_id" => $mockUser['id'],
      "items" => [
        ["item_id" => 5, "quantity" => 1, "price" => "50.00"]
      ]
    ];

    // 3. توقع استدعاء الـ Service (getCart) وإرجاع البيانات الوهمية
    $this->cartServiceMock
      ->shouldReceive('getCart')
      ->once()
      ->with($projectId, $mockUser['id']) // نتحقق من تمرير المعاملات الصحيحة
      ->andReturn($mockCartData);

    // 4. بناء الـ Request وحقن البيانات والـ attributes
    // نستخدم GET لأن تابع show عادة ما يكون GET، ونمرر project_id كـ Query Parameter
    $request = \Illuminate\Http\Request::create('/api/ecommerce/cart', 'GET', [
      'project_id' => $projectId
    ]);
    $request->attributes->set('auth_user', $mockUser);

    // 5. استدعاء التابع مباشرة من الـ Controller
    $controller = new \App\Http\Controllers\CartController($this->cartServiceMock);
    $response = $controller->show($request);

    // 6. التحقق من النتيجة
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Cart fetched successfully',
        'data' => [
          'id' => 10,
          'project_id' => $projectId,
          'user_id' => $mockUser['id']
        ]
      ]);
  }

  #[Test]
  public function it_can_update_cart_items_successfully()
  {
    // 1. تجهيز البيانات المدخلة (الـ Payload والـ User)
    $mockUser = ['id' => 1];
    $payload = [
      'project_id' => 2,
      'items' => [
        ['item_id' => 1, 'quantity' => 2],
        ['item_id' => 2, 'quantity' => 1]
      ]
    ];

    // 2. البيانات المتوقع عودتها من الـ Service بعد التحديث
    $mockUpdatedCart = [
      "id" => 2,
      "project_id" => 2,
      "user_id" => 1,
      "items" => [
        [
          "item_id" => 1,
          "quantity" => 2,
          "price" => "100.00",
          "subtotal" => "200.00"
        ],
        [
          "item_id" => 2,
          "quantity" => 1,
          "price" => "100.00",
          "subtotal" => "100.00"
        ]
      ],
      "updated_at" => "2026-03-24T21:00:00.000000Z"
    ];

    // 3. توقع استدعاء الـ Service (updateItems)
    // نتحقق من أن الـ Service يستلم الـ DTO الصحيح (يمكننا استخدام closure للتحقق من محتوى الـ DTO إذا أردت)
    $this->cartServiceMock
      ->shouldReceive('updateItems')
      ->once()
      ->andReturn($mockUpdatedCart);

    // 4. بناء الـ Request الخاص بالتحديث وحقن الـ attributes
    $request = \App\Domains\E_Commerce\Requests\UpdateCartRequest::create(
      '/api/ecommerce/cart',
      'PUT', // أو POST حسب تعريف الـ route لديك، لكن PUT هو المعياري للتحديث
      $payload
    );
    $request->attributes->set('auth_user', $mockUser);

    // إخبار الحاوية باستخدام هذا الـ Request
    $this->app->instance('request', $request);

    // 5. استدعاء التابع مباشرة من الـ Controller
    $controller = new \App\Http\Controllers\CartController($this->cartServiceMock);
    $response = $controller->update($request);

    // 6. التحقق من النتيجة
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Cart updated successfully',
        'data' => [
          'id' => 2,
          'user_id' => 1,
          'project_id' => 2
        ]
      ]);
  }

  #[Test]
  public function it_can_remove_items_from_cart_successfully()
  {
    // 1. تجهيز البيانات المدخلة (المستخدم والمشروع والعناصر المراد حذفها)
    $mockUser = ['id' => 1];
    $payload = [
      'project_id' => 2,
      'items' => [
        ['item_id' => 2],
        ['item_id' => 3]
      ]
    ];

    // 2. البيانات المتوقع عودتها (العربة بعد الحذف)
    $mockCartAfterRemoval = [
      "id" => 2,
      "project_id" => 2,
      "user_id" => 1,
      "items" => [
        [
          "item_id" => 1, // لنفترض أن العنصر رقم 1 هو الوحيد المتبقي
          "quantity" => 1,
          "price" => "100.00",
          "subtotal" => "100.00"
        ]
      ]
    ];

    // 3. توقع استدعاء الـ Service (removeItems)
    $this->cartServiceMock
      ->shouldReceive('removeItems')
      ->once()
      ->andReturn($mockCartAfterRemoval);

    // 4. بناء الـ Request وحقن البيانات والـ attributes
    $request = \App\Domains\E_Commerce\Requests\RemoveCartItemsRequest::create(
      '/api/ecommerce/cart/remove',
      'POST',
      $payload
    );
    $request->attributes->set('auth_user', $mockUser);

    // إخبار الحاوية باستخدام هذا الـ Request
    $this->app->instance('request', $request);

    // 5. استدعاء التابع مباشرة من الـ Controller
    $controller = new \App\Http\Controllers\CartController($this->cartServiceMock);
    $response = $controller->remove($request);

    // 6. التحقق من النتيجة
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Items removed successfully',
        'data' => [
          'id' => 2,
          'user_id' => 1
        ]
      ])
      ->assertJsonCount(1, 'data.items'); // التأكد من بقاء عنصر واحد فقط حسب الـ Mock
  }

  #[Test]
  public function it_can_clear_cart_successfully()
  {
    // 1. تجهيز البيانات الأساسية (المشروع والمستخدم)
    $projectId = 2;
    $mockUser = ['id' => 1];

    // 2. البيانات المتوقع عودتها من الـ Service (عربة فارغة)
    $mockEmptyCart = [
      "id" => 2,
      "project_id" => $projectId,
      "user_id" => $mockUser['id'],
      "items" => [], // المصفوفة فارغة بعد المسح
      "updated_at" => "2026-03-24T22:00:00.000000Z"
    ];

    // 3. توقع استدعاء الـ Service (clearCart) بالمعاملات الصحيحة
    $this->cartServiceMock
      ->shouldReceive('clearCart')
      ->once()
      ->with($projectId, $mockUser['id'])
      ->andReturn($mockEmptyCart);

    // 4. بناء الـ Request وحقن الـ attributes
    $request = \Illuminate\Http\Request::create('/api/ecommerce/cart/clear', 'POST', [
      'project_id' => $projectId
    ]);
    $request->attributes->set('auth_user', $mockUser);

    // إخبار الحاوية باستخدام هذا الـ Request
    $this->app->instance('request', $request);

    // 5. استدعاء التابع مباشرة من الـ Controller
    $controller = new \App\Http\Controllers\CartController($this->cartServiceMock);
    $response = $controller->clear($request);

    // 6. التحقق من النتيجة النهائية
    $testResponse = $this->createTestResponse($response, $request);

    $testResponse->assertStatus(200)
      ->assertJson([
        'message' => 'Cart cleared successfully',
        'data' => [
          'id' => 2,
          'items' => []
        ]
      ]);
  }
}
