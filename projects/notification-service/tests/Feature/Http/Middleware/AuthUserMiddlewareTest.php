<?php

namespace Tests\Feature\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\AuthUserMiddleware;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class AuthUserMiddlewareTest extends TestCase
{
  private MockInterface $authApiClientMock;
  private AuthUserMiddleware $middleware;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. تزييف الـ AuthApiClient
    $this->authApiClientMock = $this->mock(AuthApiClient::class);

    // 2. بناء الـ Middleware وحقن الـ Mock
    $this->middleware = new AuthUserMiddleware($this->authApiClientMock);
  }

  #[Test]
  public function it_returns_unauthorized_if_no_bearer_token_is_provided()
  {
    $request = Request::create('/api/any-route', 'GET');

    $next = function ($req) {
      $this->fail('Should not pass to the next middleware.');
    };

    $response = $this->middleware->handle($request, $next);

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
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('Authorization', 'Bearer bad-token');

    $this->authApiClientMock
      ->shouldReceive('getUserFromToken')
      ->once()
      ->with('bad-token')
      ->andThrow(new \Exception('Token invalid'));

    $next = function ($req) {
      $this->fail('Should not pass to the next middleware.');
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(401, $response->getStatusCode());
    $this->assertJsonStringEqualsJsonString(
      json_encode(['message' => 'Invalid or expired token']),
      $response->getContent()
    );
  }

  #[Test]
  public function it_passes_request_injects_user_and_sets_user_resolver_successfully()
  {
    $request = Request::create('/api/any-route', 'GET');
    $request->headers->set('Authorization', 'Bearer good-token');

    $mockUserData = [
      'id' => 'user-123',
      'email' => 'developer@example.com',
      'role' => 'admin'
    ];

    $this->authApiClientMock
      ->shouldReceive('getUserFromToken')
      ->once()
      ->with('good-token')
      ->andReturn($mockUserData);

    $nextCalled = false;
    $next = function ($req) use (&$nextCalled, $mockUserData) {
      $nextCalled = true;

      // 1. التحقق من حقن المصفوفة في الـ attributes بنجاح
      $injectedUser = $req->attributes->get('auth_user');
      $this->assertEquals($mockUserData, $injectedUser);

      // 2. التحقق من عمل الـ User Resolver (مهم جداً للبث والتأكد من تحويله لـ Object)
      $resolvedUser = $req->user();
      $this->assertIsObject($resolvedUser);
      $this->assertEquals('user-123', $resolvedUser->id);
      $this->assertEquals('developer@example.com', $resolvedUser->email);

      return response('Passed');
    };

    $response = $this->middleware->handle($request, $next);

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Passed', $response->getContent());
  }
}
  