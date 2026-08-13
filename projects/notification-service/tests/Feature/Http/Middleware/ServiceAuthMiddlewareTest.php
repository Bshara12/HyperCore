<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\ServiceAuthMiddleware;
use App\Services\Auth\AuthApiClient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceAuthMiddlewareTest extends TestCase
{
  #[Test]
  public function it_returns_unauthorized_when_bearer_token_is_missing()
  {
    $authClientMock = Mockery::mock(AuthApiClient::class);
    $middleware = new ServiceAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/notifications/send', 'POST');

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Service token is required.', $data['message']);
  }

  #[Test]
  public function it_returns_unauthorized_when_service_token_is_invalid()
  {
    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getServiceFromToken')
      ->once()
      ->with('invalid-token')
      ->andReturn([]);

    $middleware = new ServiceAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/notifications/send', 'POST', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
    ]);

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Unauthorized: Invalid service token.', $data['message']);
  }

  #[Test]
  public function it_handles_exceptions_and_returns_unauthorized()
  {
    Log::shouldReceive('warning')
      ->once()
      ->with('ServiceAuth failed', Mockery::any());

    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getServiceFromToken')
      ->once()
      ->with('faulty-token')
      ->andThrow(new Exception('Connection error'));

    $middleware = new ServiceAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/notifications/send', 'POST', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer faulty-token',
    ]);

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Unauthorized: Could not verify service.', $data['message']);
  }

  #[Test]
  public function it_passes_when_service_token_is_valid()
  {
    $serviceData = ['name' => 'order-service', 'id' => 1];

    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getServiceFromToken')
      ->once()
      ->with('valid-token')
      ->andReturn($serviceData);

    $middleware = new ServiceAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/notifications/send', 'POST', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer valid-token',
    ]);

    $nextCalled = false;
    $response = $middleware->handle($request, function ($req) use (&$nextCalled, $serviceData) {
      $nextCalled = true;
      $this->assertEquals($serviceData, $req->get('authenticated_service'));
      return response()->json(['success' => true]);
    });

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
  }

  protected function tearDown(): void
  {
    Mockery::close();
    parent::tearDown();
  }
}
