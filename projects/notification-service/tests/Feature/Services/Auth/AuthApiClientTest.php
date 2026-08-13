<?php

namespace Tests\Feature\Services\Auth;

use App\Services\Auth\AuthApiClient;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthApiClientTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Config::set('services.auth.url', 'https://auth.example.com');
    Config::set('services.auth.internal_api_key', 'test-internal-api-key');
  }

  // ════════════════════════════════════════════════════════════
  // 1. اختبارات التابع: getUserFromToken
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_fetches_user_from_token_successfully_and_extracts_permissions()
  {
    Http::fake([
      'auth.example.com/api/my-profile' => Http::response([
        'data' => [
          'id'    => 'user-123',
          'name'  => 'John Doe',
          'roles' => [
            [
              'permessions' => [
                ['name' => 'send-notification'],
                ['name' => 'view-reports'],
                ['name' => 'send-notification'],
              ],
            ],
            [
              'permessions' => [
                ['name' => 'delete-notification'],
              ],
            ],
          ],
        ],
      ], 200),
    ]);

    $client = new AuthApiClient();
    $user = $client->getUserFromToken('valid-token');

    $this->assertEquals('user-123', $user['id']);
    $this->assertEquals('John Doe', $user['name']);
    $this->assertEquals(
      ['send-notification', 'view-reports', 'delete-notification'],
      $user['permessions']
    );
  }

  #[Test]
  public function it_handles_user_from_token_with_empty_or_missing_roles()
  {
    Http::fake([
      'auth.example.com/api/my-profile' => Http::response([
        'data' => [
          'id'   => 'user-456',
          'name' => 'Jane Doe',
        ],
      ], 200),
    ]);

    $client = new AuthApiClient();
    $user = $client->getUserFromToken('valid-token');

    $this->assertEquals('user-456', $user['id']);
    $this->assertIsArray($user['permessions']);
    $this->assertEmpty($user['permessions']);
  }

  #[Test]
  public function it_throws_custom_exception_when_fetching_user_from_token_fails()
  {
    Http::fake([
      'auth.example.com/api/my-profile' => Http::response([
        'message' => 'Token has expired',
      ], 401),
    ]);

    // يختبر السطور 27-34 برسالك المخصصة
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Failed to fetch user from auth service: Token has expired');

    $client = new AuthApiClient();
    $client->getUserFromToken('expired-token');
  }

  // ════════════════════════════════════════════════════════════
  // 2. اختبارات التابع: getServiceFromToken
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_fetches_service_from_token_successfully()
  {
    Http::fake([
      'auth.example.com/api/get-service' => Http::response([
        'data' => [
          'service_id' => 'service-xyz',
          'name'       => 'Notification Service',
        ],
      ], 200),
    ]);

    $client = new AuthApiClient();
    $service = $client->getServiceFromToken('service-token');

    $this->assertEquals('service-xyz', $service['service_id']);
    $this->assertEquals('Notification Service', $service['name']);
  }

  #[Test]
  public function it_returns_empty_array_when_service_data_is_null()
  {
    Http::fake([
      'auth.example.com/api/get-service' => Http::response([], 200),
    ]);

    $client = new AuthApiClient();
    $service = $client->getServiceFromToken('service-token');

    $this->assertIsArray($service);
    $this->assertEmpty($service);
  }

  #[Test]
  public function it_throws_custom_exception_when_fetching_service_from_token_fails()
  {
    Http::fake([
      'auth.example.com/api/get-service' => Http::response([
        'message' => 'Invalid service token',
      ], 403),
    ]);

    // يختبر السطور 62-69 برسالك المخصصة
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Failed to fetch user from auth service: Invalid service token');

    $client = new AuthApiClient();
    $client->getServiceFromToken('invalid-token');
  }

  // ════════════════════════════════════════════════════════════
  // 3. اختبارات التابع: getUserById
  // ════════════════════════════════════════════════════════════

  #[Test]
  public function it_throws_exception_if_internal_api_key_is_not_configured()
  {
    Config::set('services.auth.internal_api_key', null);

    $this->expectException(Exception::class);
    $this->expectExceptionMessage('INTERNAL_SERVICES_API_KEY is not configured in .env');

    $client = new AuthApiClient();
    $client->getUserById('user-123');
  }

  #[Test]
  public function it_fetches_user_by_id_successfully_with_internal_api_key_header()
  {
    Http::fake([
      'auth.example.com/api/internal/users/user-123' => Http::response([
        'data' => [
          'id'    => 'user-123',
          'email' => 'user@example.com',
        ],
      ], 200),
    ]);

    $client = new AuthApiClient();
    $user = $client->getUserById('user-123');

    $this->assertEquals('user-123', $user['id']);
    $this->assertEquals('user@example.com', $user['email']);

    Http::assertSent(function ($request) {
      return $request->hasHeader('X-Internal-Api-Key', 'test-internal-api-key') &&
        $request->url() === 'https://auth.example.com/api/internal/users/user-123';
    });
  }

  #[Test]
  public function it_returns_empty_array_when_user_by_id_data_is_null()
  {
    Http::fake([
      'auth.example.com/api/internal/users/user-123' => Http::response([], 200),
    ]);

    $client = new AuthApiClient();
    $user = $client->getUserById('user-123');

    $this->assertIsArray($user);
    $this->assertEmpty($user);
  }

  #[Test]
  public function it_returns_empty_array_when_user_by_id_returns_404()
  {
    Http::fake([
      'auth.example.com/api/internal/users/non-existent' => Http::response(null, 404),
    ]);

    // يختبر السطور 100-102 حيث يرجع مصفوفة فارغة
    $client = new AuthApiClient();
    $user = $client->getUserById('non-existent');

    $this->assertIsArray($user);
    $this->assertEmpty($user);
  }

  #[Test]
  public function it_throws_custom_exception_when_user_by_id_returns_500()
  {
    Http::fake([
      'auth.example.com/api/internal/users/user-123' => Http::response([
        'message' => 'Internal Server Error',
      ], 500),
    ]);

    // يختبر السطور 104-108 برسالك المخصصة
    $this->expectException(Exception::class);
    $this->expectExceptionMessage('Failed to verify user: Internal Server Error');

    $client = new AuthApiClient();
    $client->getUserById('user-123');
  }
}
