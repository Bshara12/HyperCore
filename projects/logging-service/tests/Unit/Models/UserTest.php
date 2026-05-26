<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a user with fillable attributes', function () {
  // 1. إنشاء مستخدم جديد
  $user = User::create([
    'name'     => 'Feras Hatem',
    'email'    => 'feras@example.com',
    'password' => 'secret123',
  ]);

  // 2. التحقق من البيانات الأساسية
  expect($user->name)->toBe('Feras Hatem')
    ->and($user->email)->toBe('feras@example.com');
});

it('automatically hashes the password when creating a user', function () {
  $user = User::create([
    'name'     => 'Test User',
    'email'    => 'test@example.com',
    'password' => 'my-plain-password',
  ]);

  // التحقق من أن دالة الـ casts['password' => 'hashed'] تعمل بنجاح
  expect(Hash::check('my-plain-password', $user->password))->toBeTrue();
});

it('correctly casts email_verified_at to carbon instance', function () {
  // 1. إنشاء المستخدم بحقوله المسموحة أولاً
  $user = User::create([
    'name'     => 'Verified User',
    'email'    => 'verified@example.com',
    'password' => 'password',
  ]);

  // 2. تعيين القيمة وحفظها يدوياً لتخطي حماية الـ fillable
  $user->email_verified_at = now();
  $user->save();

  // 3. جلب نسخة فريش من قاعدة البيانات للتحقق من الـ Cast
  expect($user->fresh()->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('hides sensitive attributes from array conversion', function () {
  $user = User::create([
    'name'     => 'Hidden User',
    'email'    => 'hidden@example.com',
    'password' => 'password',
  ]);

  $userArray = $user->toArray();

  // التحقق من أن الخصائص الموجودة في $hidden لا تظهر عند تحويل الموديل إلى Array أو JSON
  expect($userArray)->not->toHaveKey('password')
    ->and($userArray)->not->toHaveKey('remember_token');
});

it('can use user factory to generate records', function () {
  // التحقق من أن الـ UserFactory المعرف عبر الـ trait يعمل بشكل سليم
  $user = User::factory()->create();

  expect($user)->toBeInstanceOf(User::class)
    ->and($user->id)->not->toBeNull();
});
