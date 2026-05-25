<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it can create a user with hashed password', function () {
  $user = User::factory()->create([
    'password' => 'secret123'
  ]);

  // التأكد من أن الـ cast (hashed) يعمل
  expect(Hash::check('secret123', $user->password))->toBeTrue();
});

test('it hides sensitive attributes', function () {
  $user = User::factory()->create();
  $array = $user->toArray();

  // التأكد من أن password و remember_token غير موجودين في الـ array (بسبب الـ $hidden)
  expect($array)->not->toHaveKey('password')
    ->and($array)->not->toHaveKey('remember_token');
});

test('it casts email_verified_at to datetime', function () {
  $user = User::factory()->create(['email_verified_at' => '2026-01-01 10:00:00']);

  expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($user->email_verified_at->year)->toBe(2026);
});
