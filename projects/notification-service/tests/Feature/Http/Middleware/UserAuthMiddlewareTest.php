<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\UserAuthMiddleware;
use App\Services\Auth\AuthApiClient;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserAuthMiddlewareTest extends TestCase
{
  #[Test]
  public function it_returns_unauthorized_when_bearer_token_is_missing()
  {
    $authClientMock = Mockery::mock(AuthApiClient::class);
    $middleware = new UserAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/in-app-notifications', 'GET');

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Authentication token is required.', $data['message']);
  }

  #[Test]
  public function it_returns_unauthorized_when_user_token_is_invalid()
  {
    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getUserFromToken')
      ->once()
      ->with('invalid-token')
      ->andReturn([]);

    $middleware = new UserAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/in-app-notifications', 'GET', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer invalid-token',
    ]);

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Unauthorized: Invalid user token.', $data['message']);
  }

  #[Test]
  public function it_handles_exceptions_and_returns_unauthorized()
  {
    Log::shouldReceive('warning')
      ->once()
      ->with('UserAuth failed', Mockery::any());

    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getUserFromToken')
      ->once()
      ->with('faulty-token')
      ->andThrow(new Exception('Connection timeout'));

    $middleware = new UserAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/in-app-notifications', 'GET', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer faulty-token',
    ]);

    $response = $middleware->handle($request, function ($req) {
      $this->fail('The next closure should not be called.');
    });

    $data = json_decode($response->getContent(), true);

    $this->assertEquals(401, $response->getStatusCode());
    $this->assertFalse($data['success']);
    $this->assertEquals('Unauthorized: Could not verify user.', $data['message']);
  }

  #[Test]
  public function it_passes_when_user_token_is_valid()
  {
    $userData = ['id' => 'user-123', 'name' => 'John Doe'];

    $authClientMock = Mockery::mock(AuthApiClient::class);
    $authClientMock->shouldReceive('getUserFromToken')
      ->once()
      ->with('valid-token')
      ->andReturn($userData);

    $middleware = new UserAuthMiddleware($authClientMock);

    $request = Request::create('/api/v1/in-app-notifications', 'GET', [], [], [], [
      'HTTP_AUTHORIZATION' => 'Bearer valid-token',
    ]);

    $nextCalled = false;
    $response = $middleware->handle($request, function ($req) use (&$nextCalled, $userData) {
      $nextCalled = true;
      $this->assertEquals($userData, $req->get('authenticated_user'));
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
