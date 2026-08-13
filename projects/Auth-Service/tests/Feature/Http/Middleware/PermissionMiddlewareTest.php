<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\PermissionMiddleware;
use Illuminate\Support\Facades\Route;

/**
 * كلاس مساعد محلي لمحاكاة حقن الصلاحيات في الـ Request
 * لتجنب خطأ تحويل الـ Closure إلى String داخل الـ Router الخاص بلارافل ✅
 */
class TestSetupPermissionsMiddleware
{
  public function handle($request, \Closure $next)
  {
    $header = $request->header('X-Test-Permissions', '');
    $request->auth_permissions = $header ? explode(',', $header) : [];

    return $next($request);
  }
}

beforeEach(function () {
  // تسجيل مسار وهمي يطلب صلاحية "edit-posts"
  Route::get('/api/test-permission-protected', function () {
    return response()->json(['message' => 'Passed Permission Middleware']);
  })->middleware([
    TestSetupPermissionsMiddleware::class, // تم الاستبدال باسم الكلاس النصي هنا 
    PermissionMiddleware::class . ':edit-posts'
  ]);
});

// =========================================================================
// اختبار حالة: الحظر (403 Forbidden) - الصلاحية المطلوبة غير موجودة
// =========================================================================
test('returns 403 when the required permission is missing from request', function () {
  $this->withHeaders([
    'X-Test-Permissions' => 'view-dashboard,create-posts' // لا تحتوي على edit-posts
  ])
    ->getJson('/api/test-permission-protected')
    ->assertStatus(403)
    ->assertJson(['error' => 'Forbidden']);
});

// =========================================================================
// اختبار حالة: الحظر (403 Forbidden) - مصفوفة الصلاحيات فارغة تماماً
// =========================================================================
test('returns 403 when the request has no permissions at all', function () {
  $this->withHeaders([
    'X-Test-Permissions' => '' // فارغة تماماً
  ])
    ->getJson('/api/test-permission-protected')
    ->assertStatus(403)
    ->assertJson(['error' => 'Forbidden']);
});

// =========================================================================
// اختبار مسار النجاح: الصلاحية موجودة ويمر الطلب بسلام (200 OK)
// =========================================================================
test('passes request when the required permission exists in request', function () {
  $this->withHeaders([
    'X-Test-Permissions' => 'view-dashboard,edit-posts,delete-posts' // تحتوي على edit-posts ✅
  ])
    ->getJson('/api/test-permission-protected')
    ->assertStatus(200)
    ->assertJson(['message' => 'Passed Permission Middleware']);
});
