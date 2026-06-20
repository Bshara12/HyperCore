<?php

namespace Tests\Unit\Repositories;

use App\Models\User;
use App\Repositories\EloquentOperationRepositories;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // 🔥 ضروري لاستخدام DB::table

// 1. قمنا بإزالة RefreshDatabase من هنا
uses(TestCase::class);

beforeEach(function () {
  // 2. تنظيف الجداول قبل كل اختبار
  Schema::dropIfExists('permession_role');
  Schema::dropIfExists('role_user');
  Schema::dropIfExists('permessions');
  Schema::dropIfExists('roles');
  Schema::dropIfExists('users');

  // 3. بناء الجداول يدوياً لتطابق هيكلية مشروعك بالضبط
  Schema::create('users', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->string('password');
    $table->boolean('is_verified')->default(false); // 🔥 أضفنا الحقل المطلوب هنا
    $table->string('otp_code')->nullable();
    $table->timestamp('otp_expires_at')->nullable();
    $table->unsignedInteger('failed_attempts')->default(0);
    $table->timestamp('locked_until')->nullable();
    $table->timestamps();
  });

  Schema::create('roles', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('guard_name')->default('web'); // أضفنا القيمة الافتراضية كما يتوقعها الفاكتوري
    $table->timestamps();
  });

  Schema::create('permessions', function ($table) {
    $table->id();
    $table->string('name');
    $table->string('guard_name');
    $table->timestamps();
  });

  Schema::create('role_user', function ($table) {
    $table->integer('user_id');
    $table->integer('role_id');
    $table->timestamps();
  });

  Schema::create('permession_role', function ($table) {
    $table->integer('role_id');
    $table->integer('permession_id');
    $table->timestamps();
  });

  $this->repository = new EloquentOperationRepositories();
});

// 4. الاختبارات
test('it gets all users', function () {
  User::factory()->count(3)->create();
  expect($this->repository->getAllUsers())->toHaveCount(3);
});

test('it assigns or updates a role to a user', function () {
  $this->repository->assginRoleToUser(1, 2);
  expect(DB::table('role_user')->where('user_id', 1)->first()->role_id)->toBe(2);

  $this->repository->assginRoleToUser(1, 3);
  expect(DB::table('role_user')->where('user_id', 1)->first()->role_id)->toBe(3);
});

test('it removes a role from a user', function () {
  DB::table('role_user')->insert(['user_id' => 1, 'role_id' => 2]);
  $this->repository->removeRoleFromUser(1);
  expect(DB::table('role_user')->where('user_id', 1)->first()->role_id)->toBe(4);
});

test('it adds a new permission successfully', function () {
  $this->repository->addPermession('edit-post');
  expect(DB::table('permessions')->where('name', 'edit-post')->exists())->toBeTrue();
});

test('it throws exception if permission exists', function () {
  $this->repository->addPermession('edit-post');
  $this->repository->addPermession('edit-post');
})->throws(\Exception::class, 'This permession is already exsist');

test('it assigns permission to a role', function () {
  $this->repository->assginPermToRole(1, 2);
  expect(DB::table('permession_role')->where('permession_id', 1)->exists())->toBeTrue();
});

test('it removes permission from a role', function () {
  DB::table('permession_role')->insert(['permession_id' => 1, 'role_id' => 2]);
  $this->repository->removePermFromRole(1, 2);
  expect(DB::table('permession_role')->count())->toBe(0);
});

test('it gets all roles', function () {
  // إدخال بيانات تجريبية
  DB::table('roles')->insert([
    ['name' => 'Admin', 'created_at' => now(), 'updated_at' => now()],
    ['name' => 'User', 'created_at' => now(), 'updated_at' => now()],
  ]);

  $roles = $this->repository->getAllRoles();

  // التحقق من العدد والبيانات
  expect($roles)->toHaveCount(2);
  expect($roles->pluck('name')->toArray())->toContain('Admin', 'User');
});

test('it gets all permissions', function () {
  // إدخال بيانات تجريبية
  DB::table('permessions')->insert([
    ['name' => 'create-post', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
    ['name' => 'edit-post', 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now()],
  ]);

  $permissions = $this->repository->getAllPermissions();

  // التحقق من العدد والبيانات
  expect($permissions)->toHaveCount(2);
  expect($permissions->pluck('name')->toArray())->toContain('create-post', 'edit-post');
});
