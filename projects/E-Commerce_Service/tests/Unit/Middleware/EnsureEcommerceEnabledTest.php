<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\EnsureEcommerceEnabled;
use App\Services\CMS\CMSApiClient;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test;

class EnsureEcommerceEnabledTest extends TestCase
{
  private MockInterface $cmsClientMock;
  private EnsureEcommerceEnabled $middleware;

  protected function setUp(): void
  {
    parent::setUp();
    $this->cmsClientMock = $this->mock(CMSApiClient::class);
    $this->middleware = new EnsureEcommerceEnabled($this->cmsClientMock);
  }

  #[Test]
  public function it_allows_access_if_ecommerce_module_is_enabled()
  {
    $request = new Request();
    // محاكاة وجود بيانات المشروع في الطلب
    $request->merge([
      'project' => [
        'enabled_modules' => ['ecommerce', 'blog']
      ]
    ]);

    $nextCalled = false;
    $response = $this->middleware->handle($request, function () use (&$nextCalled) {
      $nextCalled = true;
      return response('OK');
    });

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_aborts_with_403_if_ecommerce_module_is_disabled()
  {
    $request = new Request();
    $request->merge([
      'project' => [
        'enabled_modules' => ['blog'] // موديول التجارة الإلكترونية غير موجود
      ]
    ]);

    try {
      $this->middleware->handle($request, function () {
        $this->fail('Next closure should not be called');
      });
    } catch (HttpException $e) {
      $this->assertEquals(403, $e->getStatusCode());
      $this->assertEquals('Ecommerce module is not enabled for this project', $e->getMessage());
      return;
    }

    $this->fail('Expected HttpException 403 was not thrown');
  }

  #[Test]
  public function it_aborts_if_enabled_modules_key_is_missing()
  {
    $request = new Request();
    $request->merge([
      'project' => [] // مصفوفة فارغة تماماً
    ]);

    $this->expectException(HttpException::class);
    $this->expectExceptionMessage('Ecommerce module is not enabled for this project');

    $this->middleware->handle($request, function () {
      return response('OK');
    });
  }
}
