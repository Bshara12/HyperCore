<?php

namespace Tests\Unit\Models;

use App\Models\Permession;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can have multiple roles associated with it', function () {
  // 1. إنشاء صلاحية مع تحديد الـ guard_name المطلوب
  $permission = Permession::create([
    'name' => 'edit-posts',
    'guard_name' => 'web' // أضفنا هذا السطر لإرضاء قاعدة البيانات
  ]);

  // 2. إنشاء أدوار مع تحديد الـ guard_name أيضاً
  $role1 = Role::create(['name' => 'admin', 'guard_name' => 'web']);
  $role2 = Role::create(['name' => 'editor', 'guard_name' => 'web']);

  $permission->roles()->attach([$role1->id, $role2->id]);

  // 3. التحقق من العلاقة
  expect($permission->roles)->toHaveCount(2)
    ->and($permission->roles->first())->toBeInstanceOf(Role::class)
    ->and($permission->roles->pluck('name'))->toContain('admin', 'editor');
});

it('defines the roles relationship correctly', function () {
  $permission = new Permession();

  expect($permission->roles())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
});
