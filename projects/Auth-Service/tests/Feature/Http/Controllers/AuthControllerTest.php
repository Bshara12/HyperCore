<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\JwtService;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
  if (!File::exists(storage_path('keys'))) {
    File::makeDirectory(storage_path('keys'), 0755, true);
  }

  // استخدم هذا المفتاح العام الوهمي بدلاً من النص العادي
  $publicKey = "-----BEGIN PUBLIC KEY-----\n" .
    "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAy1Kx5NkR8qlYhlvsp\n" .
    "v9y9364Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQy\n" .
    "Y4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4p\n" .
    "P6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq\n" .
    "4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0\n" .
    "qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQyY4rM6L0qj5Yq4Yw4pP6sQIDA\n" .
    "QAB\n" .
    "-----END PUBLIC KEY-----";

  File::put(storage_path('keys/public.key'), $publicKey);

  // إنشاء الدور (كودك الأصلي)
  DB::table('roles')->insert([
    'id' => 4,
    'name' => 'User',
    'guard_name' => 'web',
    'created_at' => now(),
    'updated_at' => now()
  ]);
});

afterEach(function () {
  // تنظيف: حذف الملف بعد انتهاء الاختبارات للحفاظ على نظافة البيئة
  if (File::exists(storage_path('keys/public.key'))) {
    File::delete(storage_path('keys/public.key'));
  }
});

test('user can register successfully', function () {
  $userData = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password123',
    'password_confirmation' => 'password123'
  ];

  $response = $this->postJson('/api/register', $userData);

  $response->assertStatus(201);

  // التأكد من وجود المستخدم في قاعدة البيانات
  $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

  // التأكد من ربط المستخدم بالدور في جدول role_user
  $this->assertDatabaseHas('role_user', ['user_id' => 1, 'role_id' => 4]);
});

test('login returns token for verified user', function () {
  $user = User::factory()->create([
    'is_verified' => true,
  ]);

  // قم بتغيير 'email' إلى 'identifier'
  $loginData = [
    'identifier' => $user->email,
    'password' => 'password'
  ];

  $response = $this->postJson('/api/login', $loginData);

  $response->assertStatus(200)
    ->assertJsonStructure(['access_token', 'refresh_token', 'user']);
});

test('login returns 401 for invalid credentials', function () {
  // إنشاء مستخدم صحيح
  $user = User::factory()->create([
    'password' => bcrypt('correct-password'),
  ]);

  // محاولة تسجيل الدخول بكلمة مرور خاطئة
  $response = $this->postJson('/api/login', [
    'identifier' => $user->email,
    'password' => 'wrong-password'
  ]);

  // التأكد من استلام الخطأ 401
  $response->assertStatus(401);
});

test('login returns 403 if account is not verified', function () {
  // إنشاء مستخدم غير مفعل
  $user = User::factory()->create([
    'password' => bcrypt('password123'),
    'is_verified' => false, // تأكد أن هذا الحقل مضبوط على false
  ]);

  // محاولة تسجيل الدخول
  $response = $this->postJson('/api/login', [
    'identifier' => $user->email,
    'password' => 'password123'
  ]);

  // التأكد من استلام الخطأ 403 والرسالة الصحيحة
  $response->assertStatus(403)
    ->assertJson(['message' => 'Account Not Verified!']);
});

describe('OTP Verification', function () {
  test('verifyOTP returns 404 if user not found', function () {
    $this->postJson('/api/verify-otp', ['user_id' => 999, 'otp' => '123456'])
      ->assertStatus(404)
      ->assertJson(['message' => 'User Not Found']);
  });

  test('verifyOTP returns 422 if OTP is invalid', function () {
    $user = User::factory()->create();
    // نفترض أنك قمت بمحاكاة الـ AuthService في الـ Container أو أن الـ Service تعمل فعلياً
    $this->postJson('/api/verify-otp', ['user_id' => $user->id, 'otp' => '000000'])
      ->assertStatus(422)
      ->assertJson(['message' => 'Invalid OTP']);
  });
});

describe('Resend OTP', function () {
  test('resendOTP returns 400 if account is already verified', function () {
    $user = User::factory()->create(['is_verified' => true]);

    $this->postJson('/api/resend-otp', ['user_id' => $user->id])
      ->assertStatus(400)
      ->assertJson(['message' => 'Account Already Verified']);
  });

  test('resendOTP works successfully', function () {
    $user = User::factory()->create(['is_verified' => false]);

    $this->postJson('/api/resend-otp', ['user_id' => $user->id])
      ->assertStatus(200)
      ->assertJson(['message' => 'OTP Resent']);
  });
});

describe('Token Refresh', function () {
  test('refresh returns 401 if token is invalid', function () {
    $this->postJson('/api/refresh', ['refresh_token' => 'fake-token'])
      ->assertStatus(401);
  });

  // ملاحظة: لكي يعمل هذا الاختبار، يجب أن يكون لديك إمكانية توليد Refresh Token صالح
  // أو قم بإنشاء سجل يدوياً في قاعدة البيانات
  test('refresh returns new token if valid', function () {
    // 1. تجميد الوقت
    $now = now();
    Carbon::setTestNow($now);

    // 2. إنشاء مستخدم حقيقي في قاعدة البيانات (ضروري لـ Foreign Key)
    $user = \App\Models\User::factory()->create();

    $tokenId = 'jti-123';
    $sessionId = 'session-123';
    $futureDate = $now->copy()->addYears(1);

    // 3. إدراج السجل في جدول refresh_tokens مع تحديد user_id
    \DB::table('refresh_tokens')->insert([
      'user_id'    => $user->id, // هنا قمنا بإضافة الـ user_id المفقود
      'token_id'   => $tokenId,
      'session_id' => $sessionId,
      'revoked'    => false,
      'expires_at' => $futureDate->toDateTimeString(),
      'created_at' => $now,
      'updated_at' => $now,
    ]);

    // 4. إعداد الـ Mock
    $this->mock(\App\Services\JwtService::class, function ($mock) use ($user, $tokenId, $sessionId) {
      $mock->shouldReceive('validateToken')
        ->once()
        ->andReturn((object) [
          'sub'  => $user->id, // نستخدم نفس الـ id الذي أنشأناه
          'jti'  => $tokenId,
          'type' => 'refresh',
        ]);

      $mock->shouldReceive('generateRefreshToken')
        ->once()
        ->andReturn('new-fake-refresh-token');
    });

    // 5. إرسال الطلب
    $response = $this->postJson('/api/refresh', [
      'refresh_token' => 'fake-valid-token'
    ]);

    // 6. التحقق
    $response->assertStatus(200)
      ->assertJsonStructure(['refresh_token']);

    Carbon::setTestNow();
  });
});

test('refresh returns 401 if refresh token is expired in database', function () {
  $this->withoutMiddleware();

  // 1. محاكاة JwtService ليعيد توكن صالح نوعه 'refresh'
  $mockJwt = Mockery::mock(\App\Services\JwtService::class);
  $mockJwt->shouldReceive('validateToken')
    ->once()
    ->andReturn((object) [
      'type' => 'refresh',
      'jti'  => 'test-jti',
      'sub'  => 1
    ]);
  $this->app->instance(\App\Services\JwtService::class, $mockJwt);

  // 2. محاكاة قاعدة البيانات لتعيد سجلاً منتهي الصلاحية
  DB::shouldReceive('table')->with('refresh_tokens')->andReturnSelf();
  DB::shouldReceive('where')->andReturnSelf();
  DB::shouldReceive('first')->andReturn((object) [
    'expires_at' => now()->subDay(), // تاريخ قديم (منتهي)
    'session_id' => 'session-123'
  ]);

  // 3. إرسال الطلب
  $response = $this->postJson('/api/refresh', ['refresh_token' => 'valid-format-token']);

  // 4. التأكد من النتيجة
  $response->assertStatus(401)
    ->assertJson(['message' => 'Refresh token expired']);
});

describe('Secure Endpoints', function () {
  test('logout requires authorization', function () {
    $this->postJson('/api/logout')->assertStatus(401);
  });

  test('changePassword works with valid token', function () {
    $user = \App\Models\User::factory()->create(['password' => bcrypt('old-password')]);

    $sessionId = 'session-123';
    \DB::table('my_sessions')->insert([
      'id' => $sessionId,
      'user_id' => $user->id,
      'revoked_at' => null,
      'expires_at' => now()->addHour(),
      'created_at' => now(),
      'updated_at' => now(),
    ]);

    $this->mock(\App\Services\JwtService::class, function ($mock) use ($sessionId, $user) {
      $mock->shouldReceive('validateToken')
        ->twice() // غيرناها من once إلى twice
        ->andReturn((object) [
          'sub' => $user->id,
          'sid' => $sessionId
        ]);
    });

    $response = $this->withToken('fake-valid-token')
      ->postJson('/api/change-password', [
        'current_password' => 'old-password',
        'new_password' => 'new-password-123',
        'new_password_confirmation' => 'new-password-123'
      ]);

    $response->assertStatus(200)
      ->assertJson(['message' => 'Password changed successfully']);
  });

  test('changePassword returns 401 if token is invalid', function () {
    // هذه هي القطعة المفقودة: إيقاف الـ Middleware لهذا الاختبار
    $this->withoutMiddleware();

    // نقوم بعمل Mock للخدمة لنجعلها تعيد null
    $this->mock(\App\Services\JwtService::class, function ($mock) {
      $mock->shouldReceive('validateToken')
        ->once()
        ->andReturn(null);
    });

    // نرسل الطلب (الآن سيصل الطلب إلى الكنترولر مباشرة)
    $response = $this->postJson('/api/change-password', [
      'current_password' => 'old-password',
      'new_password' => 'new-password-123',
      'new_password_confirmation' => 'new-password-123'
    ]);

    // الآن سيتم تنفيذ الـ if في الكنترولر وستحصل على التغطية (Coverage)
    $response->assertStatus(401)
      ->assertJson(['message' => 'Unauthorized']);
  });
});

test('getByIds returns users successfully', function () {
  $user1 = User::factory()->create();
  $user2 = User::factory()->create();

  $this->postJson('/api/users/by-ids', ['ids' => [$user1->id, $user2->id]])
    ->assertStatus(200)
    ->assertJsonStructure(['data']);
});

test('verifyOTP returns success and token when data is valid', function () {
  $user = \App\Models\User::factory()->create();

  // Mock للخدمات لضمان اختبار الكنترولر فقط
  $this->mock(\App\Services\SessionService::class, function ($mock) {
    $mock->shouldReceive('create')->once()->andReturn('session-123');
  });
  $this->mock(\App\Services\JwtService::class, function ($mock) {
    $mock->shouldReceive('generateToken')->once()->andReturn('fake-jwt-token');
  });
  $this->mock(\App\Services\AuthService::class, function ($mock) {
    $mock->shouldReceive('verifyOTP')->once()->andReturn(true);
  });

  $response = $this->postJson('/api/verify-otp', [
    'user_id' => $user->id,
    'otp' => '123456'
  ]);

  $response->assertStatus(200)
    ->assertJson([
      'message' => 'Verified',
      'access_token' => 'fake-jwt-token'
    ]);
});

test('resendOTP returns 404 if user not found', function () {
  // إرسال معرف مستخدم غير موجود في قاعدة البيانات
  $response = $this->postJson('/api/resend-otp', [
    'user_id' => 999999 // رقم تعجيزي لن يكون موجوداً
  ]);

  $response->assertStatus(404)
    ->assertJson(['message' => 'User Not Found']);
});

test('logout returns 401 if authorization header is missing', function () {
  $this->withoutMiddleware(); // إيقاف الميدل وير لنختبر منطق الكنترولر نفسه

  $response = $this->postJson('/api/logout'); // بدون هيدر

  $response->assertStatus(401)
    ->assertJson(['message' => 'Unauthorized']);
});

test('logout works successfully', function () {
  $this->withoutMiddleware();

  // Mock للـ AuthService
  $this->mock(\App\Services\AuthService::class, function ($mock) {
    $mock->shouldReceive('logoutService')->once();
  });

  // للتحايل على الـ attribute المفقود، نقوم بحقن البيانات في الطلب 
  // أو نتأكد أن الكنترولر لا يعتمد على قيم null
  $response = $this->withHeader('Authorization', 'Bearer valid-token')
    ->postJson('/api/logout');

  $response->assertStatus(200)
    ->assertJson(['message' => 'Logged out successfully']);
});
