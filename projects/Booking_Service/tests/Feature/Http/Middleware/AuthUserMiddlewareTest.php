<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\AuthUserMiddleware;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class AuthUserMiddlewareTest extends TestCase
{
  #[Test]
  public function it_injects_user_data_into_request_attributes_if_token_is_valid()
  {
    // 1. إنشاء الـ Mock وتحديده كـ Instance في حاوية الخدمات
    $authClientMock = Mockery::mock(AuthApiClient::class);
    $this->app->instance(AuthApiClient::class, $authClientMock);

    // 2. الآن نطلب الـ Middleware من الحاوية (سيقوم لارافيل بحقن الـ Mock تلقائياً)
    $middleware = $this->app->make(AuthUserMiddleware::class);

    $token = 'valid-token';
    $userData = ['id' => 1, 'name' => 'Mohammad'];

    // 3. ضبط التوقعات على الـ Mock
    $authClientMock
      ->shouldReceive('getUserFromToken')
      ->once()
      ->with($token)
      ->andReturn($userData);

    // 4. تجهيز الطلب
    $request = Request::create('/test', 'GET');
    $request->headers->set('Authorization', 'Bearer ' . $token);

    // 5. التنفيذ
    $middleware->handle($request, function ($req) {
      return response('Next');
    });

    // 6. التحقق
    $this->assertEquals($userData, $request->attributes->get('auth_user'));
  }
  #[Test]
  public function it_returns_401_if_no_bearer_token_is_provided()
  {
    $authClientMock = \Mockery::mock(\App\Services\Auth\AuthApiClient::class);
    $middleware = new \App\Http\Middleware\AuthUserMiddleware($authClientMock);
    $request = \Illuminate\Http\Request::create('/test', 'GET');

    $response = $middleware->handle($request, function () {});

    $this->assertEquals(401, $response->getStatusCode());
  }
  #[Test]
  public function it_injects_user_data_if_token_is_valid()
  {
    $authClientMock = \Mockery::mock(\App\Services\Auth\AuthApiClient::class);
    $this->app->instance(\App\Services\Auth\AuthApiClient::class, $authClientMock);
    $middleware = $this->app->make(\App\Http\Middleware\AuthUserMiddleware::class);

    $userData = ['id' => 1, 'name' => 'Mohammad'];
    $authClientMock->shouldReceive('getUserFromToken')->andReturn($userData);

    $request = \Illuminate\Http\Request::create('/test', 'GET');
    $request->headers->set('Authorization', 'Bearer valid-token');

    $middleware->handle($request, function ($req) use ($userData) {
      $this->assertEquals($userData, $req->attributes->get('auth_user'));
      return response('Success');
    });
  }
  #[Test]
  public function it_sets_empty_array_if_token_is_invalid_or_user_not_found()
  {
    $authClientMock = \Mockery::mock(\App\Services\Auth\AuthApiClient::class);
    $this->app->instance(\App\Services\Auth\AuthApiClient::class, $authClientMock);
    $middleware = $this->app->make(\App\Http\Middleware\AuthUserMiddleware::class);

    // هنا التعديل: نمرر مصفوفة فارغة لأن الدالة تشترط نوع array
    $authClientMock->shouldReceive('getUserFromToken')
      ->once()
      ->with('invalid-token')
      ->andReturn([]);

    $request = \Illuminate\Http\Request::create('/test', 'GET');
    $request->headers->set('Authorization', 'Bearer invalid-token');

    $middleware->handle($request, function ($req) {
      // نتحقق أن ما تم حقنه هو المصفوفة الفارغة
      $this->assertIsArray($req->attributes->get('auth_user'));
      $this->assertEmpty($req->attributes->get('auth_user'));
      return response('Next');
    });
  }
}
