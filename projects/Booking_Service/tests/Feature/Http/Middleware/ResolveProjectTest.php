<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\ResolveProject;
use App\Services\CMS\CMSApiClient;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class ResolveProjectTest extends TestCase
{
  #[Test]
  public function it_aborts_if_x_project_id_header_is_missing()
  {
    // 1. إنشاء الـ Mock والـ Middleware
    $cmsMock = Mockery::mock(CMSApiClient::class);
    $middleware = new ResolveProject($cmsMock);

    // 2. طلب بدون الهيدر المطلوب
    $request = Request::create('/test', 'GET');

    // 3. التحقق من رمي خطأ 400
    try {
      $middleware->handle($request, function () {});
    } catch (HttpException $e) {
      $this->assertEquals(400, $e->getStatusCode());
      $this->assertEquals('X-Project-Id header is required', $e->getMessage());
    }
  }
  #[Test]
  public function it_resolves_project_and_merges_it_into_request()
  {
    // 1. تجهيز الـ Mock وبيانات المشروع الوهمية
    $cmsMock = Mockery::mock(CMSApiClient::class);
    $projectData = [
      'id' => 50,
      'name' => 'My Awesome Project',
      'enabled_modules' => ['booking']
    ];

    // 2. توقع استدعاء الخدمة وإعادة البيانات
    $cmsMock->shouldReceive('resolveProject')
      ->once()
      ->andReturn($projectData);

    $middleware = new ResolveProject($cmsMock);

    // 3. تجهيز الطلب مع الـ Header
    $request = Request::create('/test', 'GET');
    $request->headers->set('X-Project-Id', 'proj_123');

    // 4. التنفيذ
    $response = $middleware->handle($request, function ($req) use ($projectData) {
      // التحقق من أن البيانات تم دمجها (Merge) داخل الـ Request بنجاح
      $this->assertEquals(50, $req->get('project_id'));
      $this->assertEquals($projectData, $req->get('project'));
      return response('Next');
    });

    // 5. التأكد من استمرار الطلب
    $this->assertEquals('Next', $response->getContent());
  }
}
