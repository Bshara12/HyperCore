<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\AuthUserMiddleware;
use App\Services\Auth\AuthApiClient;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class AuthUserMiddlewareTest extends TestCase
{
    private MockInterface $authClientMock;
    private AuthUserMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authClientMock = $this->mock(AuthApiClient::class);
        $this->middleware = new AuthUserMiddleware($this->authClientMock);
    }

    #[Test]
    public function it_returns_401_if_no_token_is_provided()
    {
        $request = Request::create('/test', 'GET');
        // لا نضع Header Authorization هنا

        $response = $this->middleware->handle($request, function () {
            $this->fail('Next closure should not be called');
        });

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', json_decode($response->getContent())->message);
    }

    #[Test]
    public function it_sets_auth_user_attribute_and_proceeds_if_token_is_valid()
    {
        $token = 'valid-token';
        $userPayload = ['id' => 1, 'name' => 'Bshara'];
        
        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $this->authClientMock->shouldReceive('getUserFromToken')
            ->with($token)
            ->once()
            ->andReturn($userPayload);

        $nextCalled = false;
        $response = $this->middleware->handle($request, function ($req) use (&$nextCalled, $userPayload) {
            $nextCalled = true;
            // نتحقق أن المستخدم تم تخزينه فعلياً في الـ Request
            $this->assertEquals($userPayload, $req->attributes->get('auth_user'));
            return response('OK');
        });

        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function it_fails_if_auth_client_throws_exception()
    {
        $token = 'invalid-token';
        $request = Request::create('/test', 'GET');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $this->authClientMock->shouldReceive('getUserFromToken')
            ->with($token)
            ->andThrow(new \Exception('Token expired'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Token expired');

        $this->middleware->handle($request, function () {
            return response('OK');
        });
    }
}