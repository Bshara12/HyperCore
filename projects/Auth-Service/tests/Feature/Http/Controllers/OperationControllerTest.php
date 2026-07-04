<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Services\JwtService;
use App\Services\OperationServices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// أضف هذا السطر هنا:
uses(RefreshDatabase::class);

beforeEach(function () {
  // شحن قاعدة البيانات بالأدوار الأربعة الأساسية بالـ معرفات (IDs) المطلوبة
  DB::table('roles')->updateOrInsert(['id' => 1], ['name' => 'owner', 'guard_name' => 'web']);
  DB::table('roles')->updateOrInsert(['id' => 2], ['name' => 'super_admin', 'guard_name' => 'web']);
  DB::table('roles')->updateOrInsert(['id' => 3], ['name' => 'admin', 'guard_name' => 'web']);
  DB::table('roles')->updateOrInsert(['id' => 4], ['name' => 'user', 'guard_name' => 'web']);
});

test('getAllUsers returns 200 with users list for super admin', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  // سطر الحل: قم بتحديث النموذج ليجلب العلاقات من قاعدة البيانات بعد الـ Factory
  $superAdmin = $superAdmin->fresh();

  // للتأكد فقط: اطبع النتيجة قبل الطلب
  // dd(User::is_super_admin($superAdmin)); 

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)->getJson('/api/get-all-users');

  $response->assertStatus(200);
});

test('getAllUsers returns 401 if token is invalid or missing', function () {
  // إرسال طلب بتوكن غير صالح
  $response = $this->withToken('fake-invalid-token')
    ->getJson('/api/get-all-users');

  // سنقوم بتغيير التوقع ليتناسب مع ما يرسله الـ Middleware فعلياً
  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

test('getAllUsers returns 401 if user is not super admin', function () {
  // إنشاء مستخدم عادي (بدون استدعاء superAdmin() في الـ Factory)
  $user = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $user->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($user, $sessionId);

  $response = $this->withToken($token)
    ->getJson('/api/get-all-users');

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

test('getAllUsers returns 404 if user not found in database', function () {
  $user = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $user->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($user, $sessionId);

  // حذف المستخدم من قاعدة البيانات لنجعل User::find($decode->sub) تعود بـ null
  $user->delete();

  $response = $this->withToken($token)
    ->getJson('/api/get-all-users');

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong! User not found']);
});

test('assginRoleToUser returns 200 on successful assignment', function () {
  // 1. تجهيز الـ Super Admin والمستخدم المستهدف
  $superAdmin = User::factory()->superAdmin()->create();
  $targetUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // 2. إرسال طلب إسناد دور الـ Admin (ID = 3 أصبح موجوداً الآن)
  $response = $this->withToken($token)
    ->postJson('/api/assign-role-to-user', [
      'user_id' => $targetUser->id,
      'role_id' => 3,
    ]);

  // 3. التحقق من النتيجة
  $response->assertStatus(200)
    ->assertJson(['message' => 'Done']);
});

test('assginRoleToUser returns 401 if token is invalid or missing', function () {
  // إرسال طلب بتوكن عشوائي خاطئ
  $response = $this->withToken('fake-invalid-token')
    ->postJson('/api/assign-role-to-user', [
      'user_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

test('assginRoleToUser returns 401 if user is not admin or super admin', function () {
  // إنشاء مستخدم عادي (لا يملك دور إداري)
  $regularUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $regularUser->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($regularUser, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/assign-role-to-user', [
      'user_id' => 5,
      'role_id' => 3,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

test('assginRoleToUser returns 404 if assignment service fails', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // محاكاة الخدمة وجعلها تعيد false لضمان دخول الكود في شرط الفشل
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('assginRoleService')->andReturn(false);
  });

  $response = $this->withToken($token)
    ->postJson('/api/assign-role-to-user', [
      'user_id' => 999, // بيانات غير مهمة لأن الخدمة ممررة وهمياً
      'role_id' => 999,
    ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

test('removeRoleFromUser returns 200 on successful removal', function () {
  // 1. تجهيز المستخدمين
  $superAdmin = User::factory()->superAdmin()->create();
  $targetUser = User::factory()->create();

  // ربط المستخدم المستهدف بدور (مثلاً ID = 4) في جدول الـ Pivot لكي تنجح عملية الحذف
  DB::table('role_user')->insert([
    'user_id'    => $targetUser->id,
    'role_id'    => 4,
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // 2. إرسال طلب حذف الأدوار عن المستخدم المستهدف
  $response = $this->withToken($token)
    ->postJson('/api/remove-role-from-user', [
      'user_id' => $targetUser->id,
    ]);

  // 3. التحقق من النتيجة
  $response->assertStatus(200)
    ->assertJson(['message' => 'Done']);
});

test('removeRoleFromUser returns 401 if token is invalid or missing', function () {
  $response = $this->withToken('fake-invalid-token')
    ->postJson('/api/remove-role-from-user', [
      'user_id' => 1,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

test('removeRoleFromUser returns 401 if user is not admin or super admin', function () {
  $regularUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $regularUser->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($regularUser, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/remove-role-from-user', [
      'user_id' => 5,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

test('removeRoleFromUser returns 404 if target user has no roles to remove', function () {
  // 1. تجهيز الـ Super Admin ومستخدم جديد تماماً (بدون أدوار)
  $superAdmin = User::factory()->superAdmin()->create();
  $targetUser = User::factory()->create(); // مستخدم نظيف ليس له سجل في جدول role_user

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // 2. إرسال طلب الحذف (ستقوم الـ Service بإعادة false لأنها لن تجد شيئاً لتحذفه)
  $response = $this->withToken($token)
    ->postJson('/api/remove-role-from-user', [
      'user_id' => $targetUser->id,
    ]);

  // 3. التحقق من الدخول في شرط الـ 404 الأخير
  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

test('add_permession returns 200 on successful permission addition', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('addPermessionService')->andReturn(true);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // تعديل الرابط هنا إلى add-permession ليتطابق مع الـ Routes
  $response = $this->withToken($token)
    ->postJson('/api/add-permession', [
      'permession' => 'edit-articles'
    ]);

  $response->assertStatus(200)
    ->assertJson(['message' => 'The permession added successfuly']);
});

test('add_permession returns 401 if user is not super admin', function () {
  $regularUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $regularUser->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($regularUser, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/add-permession', [
      'permession' => 'edit-articles'
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

test('add_permession returns 401 if token is invalid or missing', function () {
  $response = $this->withToken('invalid-or-expired-token')
    ->postJson('/api/add-permession', [
      'permession' => 'edit-articles'
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

test('add_permession returns 200 with error message if service fails', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('addPermessionService')->andReturn(false);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/add-permession', [
      'permession' => 'edit-articles'
    ]);

  $response->assertStatus(200)
    ->assertJson(['message' => 'Something went wrong, Try again!']);
});

test('add_permession returns 401 if token is valid but user does not exist', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // حذف المستخدم لجعله null داخل الكنترولر
  $superAdmin->delete();

  $response = $this->withToken($token)
    ->postJson('/api/add-permession', [
      'permession' => 'edit-articles'
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

// 1. اختبار المسار الناجح (200 Done)
test('assign_permession_to_role returns 200 on successful assignment', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  // محاكاة نجاح السيرفيس
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('assginPermToRoleService')->andReturn(true);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/assign-permession-to-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(200)
    ->assertJson(['message' => 'Done']);
});

// 2. اختبار حماية الميدل وير (401 Invalid or expired token)
test('assign_permession_to_role returns 401 if token is invalid or missing', function () {
  $response = $this->withToken('fake-invalid-token')
    ->postJson('/api/assign-permession-to-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

// 3. اختبار عدم امتلاك صلاحية السوبر أدمن (401 Not authorized)
test('assign_permession_to_role returns 401 if user is not super admin', function () {
  $regularUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $regularUser->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($regularUser, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/assign-permession-to-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

// 4. اختبار فشل عملية الربط في السيرفيس (404 Somthig went wrong!)
test('assign_permession_to_role returns 404 if service fails', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  // إجبار السيرفيس على إرجاع false لمحاكاة الفشل
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('assginPermToRoleService')->andReturn(false);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/assign-permession-to-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

// 5. اختبار عدم وجود المستخدم في قاعدة البيانات (404 لـ Null User)
test('assign_permession_to_role returns 404 if token is valid but user does not exist', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // حذف المستخدم لجعله null عند البحث عنه بـ User::find() داخل الكنترولر
  $superAdmin->delete();

  $response = $this->withToken($token)
    ->postJson('/api/assign-permession-to-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

// 1. اختبار المسار الناجح (200 Done)
test('remove_permession_from_role returns 200 on successful permission removal', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  // محاكاة نجاح السيرفيس في حذف الصلاحية من الدور
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('removePermToRoleService')->andReturn(true);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/remove-permession-from-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(200)
    ->assertJson(['message' => 'Done']);
});

// 2. اختبار حماية الميدل وير (401 Invalid or expired token)
test('remove_permession_from_role returns 401 if token is invalid or missing', function () {
  $response = $this->withToken('fake-invalid-token')
    ->postJson('/api/remove-permession-from-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Invalid or expired token']);
});

// 3. اختبار عدم امتلاك صلاحية السوبر أدمن (401 Not authorized)
test('remove_permession_from_role returns 401 if user is not super admin', function () {
  $regularUser = User::factory()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $regularUser->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($regularUser, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/remove-permession-from-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(401)
    ->assertJson(['message' => 'Not authorized']);
});

// 4. اختبار فشل عملية الحذف في السيرفيس (404 Somthig went wrong!)
test('remove_permession_from_role returns 404 if service fails', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  // إجبار السيرفيس على إرجاع false لمحاكاة فشل الحذف
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('removePermToRoleService')->andReturn(false);
  });

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  $response = $this->withToken($token)
    ->postJson('/api/remove-permession-from-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

// 5. اختبار عدم وجود المستخدم في قاعدة البيانات (404 لـ Null User)
test('remove_permession_from_role returns 404 if token is valid but user does not exist', function () {
  $superAdmin = User::factory()->superAdmin()->create();

  $sessionId = Str::random(32);
  DB::table('my_sessions')->insert([
    'id' => $sessionId,
    'user_id' => $superAdmin->id,
    'expires_at' => now()->addHour(),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $token = app(JwtService::class)->generateToken($superAdmin, $sessionId);

  // حذف المستخدم لجعله null عند البحث عنه داخل الكنترولر بـ User::find()
  $superAdmin->delete();

  $response = $this->withToken($token)
    ->postJson('/api/remove-permession-from-role', [
      'permession_id' => 1,
      'role_id' => 2,
    ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'Somthig went wrong!']);
});

// =========================================================================
// 1. اختبارات التابع: getAllRoles
// =========================================================================

test('getAllRoles returns 200 with roles list when roles exist', function () {
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('getAllRolesService')->andReturn([
      ['id' => 1, 'name' => 'super_admin'],
      ['id' => 2, 'name' => 'admin']
    ]);
  });

  $response = $this->getJson('/api/get-all-roles');

  $response->assertStatus(200)
    ->assertJsonStructure(['roles']);
});

test('getAllRoles returns 200 with empty message when no roles exist', function () {
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('getAllRolesService')->andReturn([]);
  });

  $response = $this->getJson('/api/get-all-roles');

  $response->assertStatus(200)
    ->assertJson(['message' => 'Ther is no roles']);
});

// =========================================================================
// 2. اختبارات التابع: getAllPermissions
// =========================================================================

test('getAllPermissions returns 200 with permissions list when permissions exist', function () {
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('getAllPermissionsService')->andReturn([
      ['id' => 1, 'name' => 'add-permession'],
      ['id' => 2, 'name' => 'delete-user']
    ]);
  });

  $response = $this->getJson('/api/get-all-permissions');

  $response->assertStatus(200)
    ->assertJsonStructure(['permissions']);
});

test('getAllPermissions returns 200 with empty message when no permissions exist', function () {
  $this->mock(OperationServices::class, function ($mock) {
    $mock->shouldReceive('getAllPermissionsService')->andReturn([]);
  });

  $response = $this->getJson('/api/get-all-permissions');

  $response->assertStatus(200)
    ->assertJson(['message' => 'Ther is no permissions']);
});