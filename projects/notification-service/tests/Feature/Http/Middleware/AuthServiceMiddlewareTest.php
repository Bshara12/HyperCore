<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\AuthServiceMiddleware;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class AuthServiceMiddlewareTest extends TestCase
{
  private MockInterface $authApiClientMock;
  private AuthServiceMiddleware $middleware;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. تزييف عميل الـ API الخاص بالـ Auth
    $this->authApiClientMock = $this->mock(AuthApiClient::class);

    // 2. بناء الـ Middleware وحقن الـ Mock داخله
    $this->middleware = new AuthServiceMiddleware($this->authApiClientMock);
  }

  #[Test]
  public function it_returns_unauthorized_if_no_bearer_token_is_provided()
  {
    // إنشاء Request فارغ تماماً بدون Authorization Header
    $request = Request::create('/api/any-route', 'GET');

    // الـ Closure الذي يمثل الخطوة التالية (Next) - لن يتم استدعاؤه في هذا الفحص
    $next = function ($req) {
      $this->fail('Should not pass to the next middleware/controller.');
    };

    $response = $this->middleware->handle($request, $next);

    // التحقق من النتيجة والـ Status Code
    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(401, $response->getStatusCode());

    $this->assertJsonStringEqualsJsonString(
      json_encode(['message' => 'Unauthorized']),
      $response->getContent()
    );
  }

  #[Test]
  public function it_returns_invalid_token_if_api_client_throws_exception()
  {
    // إنشاء Request يحتوي على Token غير صالح
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('Authorization', 'Bearer invalid-token-123');

    // ضبط الـ Mock ليرمي Throwable عند محاولة فحص الـ Token
    $this->authApiClientMock
      ->shouldReceive('getServiceFromToken')
      ->once()
      ->with('invalid-token-123')
      ->andThrow(new \Exception('Token expired or manipulated'));

    $next = function ($req) {
      $this->fail('Should not pass to the next middleware/controller.');
    };

    $response = $this->middleware->handle($request, $next);

    // التحقق من معالجة الـ Catch بنجاح وإرجاع 401 للعميل
    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(401, $response->getStatusCode());

    $this->assertJsonStringEqualsJsonString(
      json_encode(['message' => 'Invalid or expired token']),
      $response->getContent()
    );
  }

  #[Test]
  public function it_passes_request_and_sets_attributes_if_token_is_valid()
  {
    // إنشاء Request يحتوي على Token صحيح
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('Authorization', 'Bearer valid-token-789');

    $mockServiceData = [
      'id' => 'service-xyz',
      'name' => 'Payment Service',
      'scopes' => ['read', 'write']
    ];

    // ضبط الـ Mock ليعيد مصفوفة البيانات بنجاح عند مطابقة الـ Token
    $this->authApiClientMock
      ->shouldReceive('getServiceFromToken')
      ->once()
      ->with('valid-token-789')
      ->andReturn($mockServiceData);

    // الـ Closure الذي يمثل الخطوة التالية - نتحقق داخله من حقن الـ Attribute بنجاح
    $nextCalled = false;
    $next = function ($req) use (&$nextCalled, $mockServiceData) {
      $nextCalled = true;

      // التحقق من تحويل المصفوفة لـ Object وحقنها في الـ attributes
      $injectedService = $req->attributes->get('auth_service_client');

      $this->assertNotNull($injectedService);
      $this->assertEquals('service-xyz', $injectedService->id);
      $this->assertEquals('Payment Service', $injectedService->name);

      // إرجاع رد وهمي لإنهاء المعاملة بسلام
      return response('Passed');
    };

    $response = $this->middleware->handle($request, $next);

    // التأكيد النهائي على أن الطلب عبر الـ Middleware بالكامل ووصل للمرحلة التالية
    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Passed', $response->getContent());
  }
}
