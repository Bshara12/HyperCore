<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use App\Services\Auth\AuthApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class AuthApiClientTest extends TestCase
{
  protected AuthApiClient $authClient;
  protected string $fakeUrl = 'https://auth-service.test';

  protected function setUp(): void
  {
    parent::setUp();

    // ضبط الرابط الوهمي في الإعدادات
    Config::set('services.auth_service.url', $this->fakeUrl);

    $this->authClient = new AuthApiClient();
  }

  #[Test]
  public function it_fetches_user_and_transforms_roles_to_permissions_successfully(): void
  {
    $token = 'valid-token';

    // محاكاة بيانات مستخدم مع أدوار وصلاحيات مكررة لاختبار الـ unique والـ flatMap
    $fakeApiResponse = [
      'data' => [
        'id' => 1,
        'name' => 'Gemini User',
        'email' => 'user@example.com',
        'roles' => [
          [
            'name' => 'admin',
            'permessions' => [ // الالتزام بالإملاء الموجود في كود الخدمة
              ['name' => 'manage_users'],
              ['name' => 'view_reports'],
            ]
          ],
          [
            'name' => 'editor',
            'permessions' => [
              ['name' => 'view_reports'], // مكررة
              ['name' => 'edit_content'],
            ]
          ]
        ]
      ]
    ];

    Http::fake([
      "{$this->fakeUrl}/api/my-profile" => Http::response($fakeApiResponse, 200)
    ]);

    $result = $this->authClient->getUserFromToken($token);

    // التحقق من صحة البيانات الأساسية
    $this->assertEquals(1, $result['id']);
    $this->assertEquals('Gemini User', $result['name']);

    // التحقق من معالجة الصلاحيات (Flattened, Unique, and Indexed)
    $expectedPermissions = ['manage_users', 'view_reports', 'edit_content'];
    $this->assertEquals($expectedPermissions, $result['permissions']);

    // التأكد من إرسال التوكن الصحيح في الرأس (Header)
    Http::assertSent(function ($request) use ($token) {
      return $request->hasHeader('Authorization', 'Bearer ' . $token) &&
        $request->url() === "{$this->fakeUrl}/api/my-profile";
    });
  }

  #[Test]
  public function it_throws_exception_with_json_message_when_request_fails(): void
  {
    $token = 'invalid-token';
    $errorMessage = 'Invalid or expired token';

    Http::fake([
      "{$this->fakeUrl}/api/my-profile" => Http::response([
        'message' => $errorMessage
      ], 401)
    ]);

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Failed to fetch user from auth service: ' . $errorMessage);

    $this->authClient->getUserFromToken($token);
  }

  #[Test]
  public function it_throws_exception_with_body_summary_when_json_message_is_missing(): void
  {
    $token = 'error-token';
    $rawError = 'Internal Server Error raw string';

    // محاكاة خطأ 500 بدون JSON (فقط نص خام)
    Http::fake([
      "{$this->fakeUrl}/api/my-profile" => Http::response($rawError, 500)
    ]);

    $this->expectException(\Exception::class);
    // التحقق من أن الخدمة أخذت أول 200 حرف من الـ body عند غياب الـ message
    $this->expectExceptionMessage('Failed to fetch user from auth service: ' . $rawError);

    $this->authClient->getUserFromToken($token);
  }
}
