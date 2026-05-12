<?php

namespace Tests\Unit\Http\Middleware;

use Tests\TestCase;
use App\Http\Middleware\CheckPermission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PHPUnit\Framework\Attributes\Test; // استيراد الـ Attribute

class CheckPermissionTest extends TestCase
{
  private CheckPermission $middleware;

  protected function setUp(): void
  {
    parent::setUp();
    $this->middleware = new CheckPermission();
  }
  #[Test]
  public function it_returns_403_if_user_does_not_have_permission()
  {
    // 1. تجهيز مستخدم بصلاحيات محدودة
    $user = [
      'id' => 1,
      'permissions' => ['view_posts'] // لا يملك صلاحية 'delete_posts'
    ];

    // 2. تجهيز الطلب وحقن المستخدم فيه
    $request = Request::create('/test', 'DELETE');
    $request->attributes->set('auth_user', $user);

    // 3. تنفيذ الـ Middleware مع طلب صلاحية 'delete_posts'
    $response = $this->middleware->handle($request, function () {
      return response('Next');
    }, 'delete_posts');

    // 4. التحقق من الرفض
    $this->assertInstanceOf(JsonResponse::class, $response);
    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Forbidden', $response->getData()->message);
  }
  #[Test]
  public function it_allows_request_if_user_has_required_permission()
  {
    // 1. تجهيز مستخدم يملك الصلاحية المطلوبة
    $user = [
      'id' => 1,
      'permissions' => ['edit_resource', 'view_resource']
    ];

    // 2. تجهيز الطلب
    $request = Request::create('/test', 'POST');
    $request->attributes->set('auth_user', $user);

    // 3. تنفيذ الـ Middleware مع طلب صلاحية 'edit_resource'
    $response = $this->middleware->handle($request, function () {
      return response('Passed');
    }, 'edit_resource');

    // 4. التحقق من المرور بنجاح
    $this->assertEquals('Passed', $response->getContent());
  }
}
