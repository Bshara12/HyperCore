<?php

namespace Tests\Unit\Models;

use App\Models\User;
use App\Models\Role;
use App\Models\Permession;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
  // تنظيف الجداول مسبقاً
  Schema::dropIfExists('permession_role');
  Schema::dropIfExists('role_user');
  Schema::dropIfExists('permessions');
  Schema::dropIfExists('roles');
  Schema::dropIfExists('users');

  // 1. بناء جدول المستخدمين بناءً على حقول الـ fillable والـ casts لديك
  Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_verified')->default(false);
    $table->string('otp_code')->nullable();
    $table->dateTime('otp_expires_at')->nullable();
    $table->dateTime('locked_until')->nullable();
    $table->dateTime('email_verified_at')->nullable();
    $table->rememberToken();
    $table->timestamps();
  });

  // 2. بناء جدول الأدوار
  Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
  });

  // 3. جدول الصلاحيات
  Schema::create('permessions', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
  });

  // 4. جدول الوسيط role_user (مع الالتزام بالترتيب الحالي لكودك)
  Schema::create('role_user', function (Blueprint $table) {
    $table->integer('user_id');
    $table->integer('role_id');
    $table->timestamps();
  });

  // 5. جدول الوسيط permession_role للربط بين الدور والصلاحية
  Schema::create('permession_role', function (Blueprint $table) {
    $table->foreignId('role_id');
    $table->foreignId('permession_id');
    $table->timestamps();
  });
});

// ─── كود الفحص والتأكيدات ──────────────────────────────────────────────────

// 1. اختبار الـ Casts والـ Fillable
test('it correctly casts fields and handles fillable attributes', function () {
  $user = User::create([
    'name'           => 'Ahmad',
    'email'          => 'ahmad@example.com',
    'password'       => 'secret123',
    'is_verified'    => 1, // سنمرره كرقم للتأكد من الـ cast لـ boolean
    'otp_code'       => '1234',
    'otp_expires_at' => '2026-06-05 18:00:00',
    'locked_until'   => '2026-06-05 19:00:00',
  ]);

  expect($user->is_verified)->toBeTrue(); // فحص الـ boolean cast
  expect($user->otp_expires_at)->toBeInstanceOf(Carbon::class); // فحص الـ datetime cast
  expect($user->locked_until)->toBeInstanceOf(Carbon::class);
});

// 2. اختبار الـ Hidden Fields عند تحويل الموديل إلى مصفوفة (Serialization)
test('it hides specific attributes during serialization', function () {
  $user = User::create([
    'name'     => 'Samir',
    'email'    => 'samir@example.com',
    'password' => 'password',
    'otp_code' => '9988',
  ]);

  $array = $user->toArray();

  expect($array)->not->toHaveKey('password');
  expect($array)->not->toHaveKey('otp_code');
});

// 3. اختبار علاقة الأدوار المستقيمة (roles relationship)
test('a user belongs to many roles', function () {
  $user = User::create(['name' => 'User1', 'email' => 'u1@test.com', 'password' => '123']);
  $role = Role::create();

  $user->roles()->attach($role->id);

  expect($user->roles)->toHaveCount(1);
  expect($user->roles->first()->id)->toBe($role->id);
});

// 4. اختبار تجميع الصلاحيات الفريدة (permessions method)
test('it aggregates unique permissions through user roles', function () {
  $user = User::create(['name' => 'User2', 'email' => 'u2@test.com', 'password' => '123']);

  $role1 = Role::create();
  $role2 = Role::create();

  $permission1 = Permession::create();
  $permission2 = Permession::create();

  // نربط الصلاحية الأولى بالدور الأول، والصلاحية الثانية بالدورين معاً (لإثبات الـ unique)
  $role1->permessions()->attach([$permission1->id, $permission2->id]);
  $role2->permessions()->attach([$permission2->id]);

  // نربط المستخدم بالدورين
  $user->roles()->attach([$role1->id, $role2->id]);

  $userPermissions = $user->permessions();

  // التأكيد: يجب أن نحصل على صلاحيتين فقط بدون تكرار للثانية بفضل flatten() و unique()
  expect($userPermissions)->toHaveCount(2);
  expect($userPermissions->pluck('id')->toArray())->toContain($permission1->id, $permission2->id);
});

// 5. اختبار دوال التحقق الثابتة (Static Role Methods: Owner, Super Admin, Admin, User)
test('it correctly identifies roles using static methods', function () {
  // إنشاء مستخدمين مختلفين للفحص
  $ownerUser = User::create(['name' => 'Owner', 'email' => 'owner@test.com', 'password' => '123']);
  $superAdminUser = User::create(['name' => 'Super', 'email' => 'super@test.com', 'password' => '123']);
  $adminUser = User::create(['name' => 'Admin', 'email' => 'admin@test.com', 'password' => '123']);
  $normalUser = User::create(['name' => 'Normal', 'email' => 'normal@test.com', 'password' => '123']);
  $noRoleUser = User::create(['name' => 'NoRole', 'email' => 'norole@test.com', 'password' => '123']);

  // ربط يدوي مبني على الـ IDs الثابتة في الكود الخاص بك (1, 2, 3, 4)
  DB::table('role_user')->insert([
    ['user_id' => $ownerUser->id, 'role_id' => 1],
    ['user_id' => $superAdminUser->id, 'role_id' => 2],
    ['user_id' => $adminUser->id, 'role_id' => 3],
    ['user_id' => $normalUser->id, 'role_id' => 4],
  ]);

  // التأكيد على المسارات الصحيحة (True Paths)
  expect(User::is_owner($ownerUser))->toBeTrue();
  expect(User::is_super_admin($superAdminUser))->toBeTrue();
  expect(User::is_admin($adminUser))->toBeTrue();
  expect(User::is_user($normalUser))->toBeTrue();

  // التأكيد على المسارات الخاطئة (False Paths للتأكد من تغطية الـ return false)
  expect(User::is_owner($noRoleUser))->toBeFalse();
  expect(User::is_super_admin($noRoleUser))->toBeFalse();
  expect(User::is_admin($noRoleUser))->toBeFalse();
  expect(User::is_user($noRoleUser))->toBeFalse();
});
