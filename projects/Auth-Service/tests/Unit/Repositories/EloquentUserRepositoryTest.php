<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\EloquentUserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
  // تنظيف الجداول
  Schema::dropIfExists('token_blacklist');
  Schema::dropIfExists('refresh_tokens');
  Schema::dropIfExists('my_sessions');
  Schema::dropIfExists('role_user');
  Schema::dropIfExists('users');

  // إنشاء الجداول
  Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->timestamps();
  });

  Schema::create('role_user', function (Blueprint $table) {
    $table->integer('user_id');
    $table->integer('role_id');
    $table->timestamps();
  });

  Schema::create('my_sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();
  });

  Schema::create('refresh_tokens', function (Blueprint $table) {
    $table->string('session_id');
    $table->integer('user_id');
    $table->boolean('revoked')->default(false);
    $table->timestamp('revoked_at')->nullable();
    $table->timestamps();
  });

  Schema::create('token_blacklist', function (Blueprint $table) {
    $table->string('token_id');
    $table->timestamp('expires_at');
    $table->timestamps();
  });

  $this->repository = new EloquentUserRepository();
});

test('it creates a user and assigns default role 4', function () {
  $data = ['name' => 'Test', 'email' => 'test@test.com', 'password' => '123456'];
  $user = $this->repository->create($data);

  expect($user->id)->not->toBeNull();
  // التأكد من إضافة الدور الافتراضي
  $role = DB::table('role_user')->where('user_id', $user->id)->first();
  expect($role->role_id)->toBe(4);
});

test('it finds user by email', function () {
  $user = User::create(['name' => 'A', 'email' => 'a@b.com', 'password' => '123']);
  $found = $this->repository->findByEmail('a@b.com');
  expect($found->id)->toBe($user->id);
});

test('it updates password', function () {
  $user = User::create(['name' => 'A', 'email' => 'a@b.com', 'password' => 'old']);
  $this->repository->updatePassword($user->id, 'new-hash');
  expect($user->fresh()->password)->toBe('new-hash');
});

test('it revokes sessions and tokens correctly', function () {
  // تجهيز البيانات
  DB::table('my_sessions')->insert(['id' => 'sess1', 'created_at' => now(), 'updated_at' => now()]);
  DB::table('refresh_tokens')->insert(['session_id' => 'sess1', 'user_id' => 1, 'created_at' => now(), 'updated_at' => now()]);

  // تجهيز كائن $decoded (mock)
  $decoded = (object) ['jti' => 'jwt-123', 'exp' => time() + 3600, 'sub' => 1];

  $this->repository->revoke('sess1', $decoded);

  // التأكيد
  expect(DB::table('my_sessions')->where('id', 'sess1')->first()->revoked_at)->not->toBeNull();
  expect(DB::table('token_blacklist')->where('token_id', 'jwt-123')->exists())->toBeTrue();
  expect(DB::table('refresh_tokens')->where('user_id', 1)->first()->revoked)->toBe(1);
});

test('it gets users by ids', function () {
  User::create(['name' => 'U1', 'email' => 'u1@t.com', 'password' => '1']);
  User::create(['name' => 'U2', 'email' => 'u2@t.com', 'password' => '1']);

  $users = $this->repository->getUsersByIds([1, 2]);
  expect($users)->toHaveCount(2);
  expect($users->first()->name)->toBe('U1');
});

test('it finds user by id', function () {
  // 1. إنشاء مستخدم للتجربة
  $user = User::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => 'password123'
  ]);

  // 2. البحث عن المستخدم
  $found = $this->repository->findById($user->id);

  // 3. التأكد من النتيجة
  expect($found)->not->toBeNull();
  expect($found->id)->toBe($user->id);
});

test('it returns null when user not found by id', function () {
  // البحث عن معرف غير موجود
  $found = $this->repository->findById(9999);

  expect($found)->toBeNull();
});

test('it updates a user', function () {
  // 1. إنشاء مستخدم
  $user = User::create([
    'name' => 'Old Name',
    'email' => 'old@example.com',
    'password' => 'password123'
  ]);

  // 2. تحديث البيانات
  $result = $this->repository->update($user, ['name' => 'New Name']);

  // 3. التأكد من أن النتيجة true وأن البيانات في قاعدة البيانات قد تغيرت
  expect($result)->toBeTrue();
  expect($user->fresh()->name)->toBe('New Name');
});
