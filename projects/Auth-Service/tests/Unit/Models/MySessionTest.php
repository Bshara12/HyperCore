<?php

namespace Tests\Unit\Models;

use App\Models\MySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('generates a ULID automatically when creating a session', function () {
  $session = MySession::create([
    'user_id'          => 1,
    'last_activity_at' => now(),
    'expires_at'       => now()->addDays(1), // أضفنا هذا السطر لإرضاء الـ Constraint
  ]);

  expect($session->id)->not->toBeNull()
    ->and(Str::isUlid($session->id))->toBeTrue();
});

it('does not override a manually set ULID', function () {
  $manualId = (string) Str::ulid();

  $session = MySession::create([
    'id'               => $manualId,
    'user_id'          => 1,
    'last_activity_at' => now(),
    'expires_at'       => now()->addDays(1), // أضفنا هذا السطر أيضاً
  ]);

  expect($session->id)->toBe($manualId);
});

it('correctly casts date attributes to Carbon instances', function () {
  $session = MySession::create([
    'user_id'          => 1,
    'last_activity_at' => '2026-05-15 10:00:00',
    'expires_at'       => '2026-05-16 10:00:00', // موجود مسبقاً هنا
    'revoked_at'       => '2026-05-15 12:00:00',
  ]);

  expect($session->last_activity_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($session->expires_at->format('Y-m-d'))->toBe('2026-05-16')
    ->and($session->revoked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});

it('has non-incrementing string keys', function () {
  $session = new MySession();

  expect($session->getIncrementing())->toBeFalse()
    ->and($session->getKeyType())->toBe('string');
});
