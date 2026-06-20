<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use App\Models\User;
use App\Models\Permession;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
  // 🔥 تنظيف الجداول القديمة أولاً لمنع تعارض الـ SQLite في التست الثاني
  Schema::dropIfExists('permession_role');
  Schema::dropIfExists('role_user');
  Schema::dropIfExists('permessions');
  Schema::dropIfExists('users');
  Schema::dropIfExists('roles');

  // إعادة بناء الهيكل بشكل نقي لكل تست
  Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
  });

  Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
  });

  Schema::create('permessions', function (Blueprint $table) {
    $table->id();
    $table->timestamps();
  });

  Schema::create('role_user', function (Blueprint $table) {
    $table->integer('user_id');
    $table->integer('role_id');
    $table->timestamps();
  });

  Schema::create('permession_role', function (Blueprint $table) {
    $table->foreignId('role_id');
    $table->foreignId('permession_id');
    $table->timestamps();
  });
});

// ─── كود الفحص والتأكيدات ──────────────────────────────────────────────────

test('a role can belong to many users and tracks timestamps', function () {
  $role = Role::create();
  $user = User::create();

  $role->users()->attach($user->id);

  expect($role->users)->toHaveCount(1);
  expect($role->users->first()->id)->toBe($user->id);
  expect($role->users->first()->pivot->created_at)->not->toBeNull();
});

test('a role can belong to many permessions and tracks timestamps', function () {
  $role = Role::create();
  $permission = Permession::create();

  $role->permessions()->attach($permission->id);

  expect($role->permessions)->toHaveCount(1);
  expect($role->permessions->first()->id)->toBe($permission->id);
  expect($role->permessions->first()->pivot->created_at)->not->toBeNull();
});
