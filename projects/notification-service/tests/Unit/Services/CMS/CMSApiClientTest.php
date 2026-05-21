<?php

namespace Tests\Unit\Services\CMS;

use Tests\TestCase;
use App\Services\CMS\CMSApiClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class CMSApiClientTest extends TestCase
{
  protected CMSApiClient $cmsClient;
  protected string $fakeUrl = 'https://cms-service.test';

  protected function setUp(): void
  {
    parent::setUp();
    Config::set('services.cms.url', $this->fakeUrl);
    $this->cmsClient = new CMSApiClient();
  }

  /**
   * دالة مساعدة لعمل Mock للهيدرز بدون كسر الـ Request في الـ Container
   */
  protected function mockRequestHeader(string $key, $value)
  {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_' . strtoupper(str_replace('-', '_', $key)) => $value]);
    $this->app->instance('request', $request);
  }

  #[Test]
  public function it_resolves_project_successfully(): void
  {
    $this->mockRequestHeader('X-Project-Id', 'proj-123');

    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response(['original' => ['id' => 1]], 200)
    ]);

    $result = $this->cmsClient->resolveProject();
    $this->assertEquals(1, $result['id']);
  }

  #[Test]
  public function it_throws_exception_when_resolve_fails(): void
  {
    $this->mockRequestHeader('X-Project-Id', 'proj-123');
    $errorMessage = 'Project not found';

    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response(['message' => $errorMessage], 404)
    ]);

    try {
      $this->cmsClient->resolveProject();
      $this->fail('Exception was not thrown');
    } catch (\Exception $e) {
      $this->assertStringContainsString($errorMessage, $e->getMessage());
    }
  }

  #[Test]
  public function it_handles_json_fallback_structure(): void
  {
    $this->mockRequestHeader('X-Project-Id', 'proj-123');

    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response(['id' => 999], 200)
    ]);

    $result = $this->cmsClient->resolveProject();
    $this->assertEquals(999, $result['id']);
  }

  #[Test]
  public function it_uses_raw_body_when_json_message_is_missing(): void
  {
    $this->mockRequestHeader('X-Project-Id', 'proj-123');
    $rawError = 'Critical server error without json key';

    // محاكاة استجابة فاشلة (500) بدون مفتاح 'message' في الـ JSON
    // أو ببساطة استجابة نصية خام لضمان عمل substr
    Http::fake([
      "{$this->fakeUrl}/api/projects/resolve" => Http::response($rawError, 500)
    ]);

    try {
      $this->cmsClient->resolveProject();
      $this->fail('Exception was not thrown');
    } catch (\Exception $e) {
      // التحقق من أن الخطأ يحتوي على النص الخام
      $this->assertStringContainsString('Failed to resolve project in CMS: ' . $rawError, $e->getMessage());
    }
  }
}
