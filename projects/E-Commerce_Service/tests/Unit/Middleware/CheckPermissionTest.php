<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\CheckPermission;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

class CheckPermissionTest extends TestCase
{
  private CheckPermission $middleware;

  protected function setUp(): void
  {
    parent::setUp();
    $this->middleware = new CheckPermission();
  }

  #[Test]
  public function it_allows_access_if_user_has_required_permission()
  {
    $request = Request::create('/test', 'GET');

    // محاكاة وجود مستخدم في الـ attributes مع صلاحية معينة
    $request->attributes->set('auth_user', [
      'id' => 1,
      'permissions' => ['view_wishlist', 'edit_wishlist']
    ]);

    $nextCalled = false;
    $response = $this->middleware->handle($request, function () use (&$nextCalled) {
      $nextCalled = true;
      return response('OK');
    }, 'view_wishlist'); // تمرير الصلاحية المطلوبة كـ Argument ثالث

    $this->assertTrue($nextCalled);
    $this->assertEquals(200, $response->getStatusCode());
  }

  #[Test]
  public function it_returns_403_if_user_does_not_have_permission()
  {
    $request = Request::create('/test', 'GET');

    // مستخدم بصلاحيات مختلفة
    $request->attributes->set('auth_user', [
      'id' => 1,
      'permissions' => ['only_reading']
    ]);

    $response = $this->middleware->handle($request, function () {
      $this->fail('Next closure should not be called');
    }, 'delete_wishlist'); // طلب صلاحية غير موجودة لدى المستخدم

    $this->assertEquals(403, $response->getStatusCode());
    $this->assertEquals('Forbidden', json_decode($response->getContent())->message);
  }
}
