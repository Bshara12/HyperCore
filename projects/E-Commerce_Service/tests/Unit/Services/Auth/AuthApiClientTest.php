<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Services\Auth\AuthApiClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class AuthApiClientTest extends TestCase
{
  private AuthApiClient $authApiClient;

  protected function setUp(): void
  {
    parent::setUp();
    // منع أي طلبات HTTP حقيقية أثناء الاختبار
    Http::preventStrayRequests();
    $this->authApiClient = new AuthApiClient();
  }

  #[Test]
  public function it_fetches_user_data_and_maps_permissions_correctly()
  {
    $token = 'valid-token';
    $mockResponse = [
      'data' => [
        'id' => 1,
        'name' => 'Bshara',
        'roles' => [
          [
            'permessions' => [
              ['name' => 'view_orders'],
              ['name' => 'create_orders']
            ]
          ],
          [
            'permessions' => [
              ['name' => 'view_orders'], // مكرر للتأكد من عمل unique
              ['name' => 'delete_orders']
            ]
          ]
        ]
      ]
    ];

    Http::fake([
      '*/api/my-profile' => Http::response($mockResponse, 200)
    ]);

    $result = $this->authApiClient->getUserFromToken($token);

    // التحقق من دمج الصلاحيات وإزالة التكرار
    $this->assertEquals(['view_orders', 'create_orders', 'delete_orders'], $result['permissions']);
    $this->assertEquals(1, $result['id']);

    Http::assertSent(function ($request) use ($token) {
      return $request->hasHeader('Authorization', 'Bearer ' . $token);
    });
  }

  #[Test]
  public function it_throws_exception_with_json_message_on_failure()
  {
    Http::fake([
      '*/api/my-profile' => Http::response(['message' => 'Invalid Token'], 401)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to fetch user from auth service: Invalid Token');

    $this->authApiClient->getUserFromToken('invalid-token');
  }

  #[Test]
  public function it_throws_exception_with_body_fallback_on_failure()
  {
    // محاكاة خطأ لا يعيد JSON (مثل خطأ من Nginx)
    $rawError = 'Internal Server Error';
    Http::fake([
      '*/api/my-profile' => Http::response($rawError, 500)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to fetch user from auth service: ' . $rawError);

    $this->authApiClient->getUserFromToken('token');
  }

  #[Test]
  public function it_truncates_long_body_fallbacks()
  {
    $longError = str_repeat('A', 300);
    $expectedError = str_repeat('A', 200); // سيتم قصه عند 200 حرف

    Http::fake([
      '*/api/my-profile' => Http::response($longError, 500)
    ]);

    try {
      $this->authApiClient->getUserFromToken('token');
    } catch (\Exception $e) {
      $this->assertStringContainsString($expectedError, $e->getMessage());
      $this->assertFalse(str_contains($e->getMessage(), str_repeat('A', 201)));
      return;
    }

    $this->fail('Exception was not thrown');
  }

  #[Test]
  public function it_handles_roles_with_no_permissions()
  {
    $mockResponse = [
      'data' => [
        'id' => 1,
        'roles' => [
          ['permessions' => []]
        ]
      ]
    ];

    Http::fake([
      '*/api/my-profile' => Http::response($mockResponse, 200)
    ]);

    $result = $this->authApiClient->getUserFromToken('token');

    $this->assertIsArray($result['permissions']);
    $this->assertEmpty($result['permissions']);
  }
}
