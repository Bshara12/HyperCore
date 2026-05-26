<?php

namespace Tests\Unit\Domains\Search; // تأكد من مسار النيم سبيس الخاص بملفات الاختبار

use App\Domains\Search\Models\UserSearchLog; // <--- هذه كانت مفقودة
use App\Domains\Search\Models\UserClickLog;  // <--- هذه كانت مفقودة
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it has clicks relationship', function () {
  $searchLog = UserSearchLog::factory()->create();

  // إنشاء عدة نقرات مرتبطة بهذا البحث
  UserClickLog::factory()->count(3)->create(['search_log_id' => $searchLog->id]);

  expect($searchLog->clicks)->toHaveCount(3)
    ->and($searchLog->clicks->first())->toBeInstanceOf(UserClickLog::class);
});

test('it casts searched_at to datetime', function () {
  $log = UserSearchLog::factory()->create(['searched_at' => '2026-05-24 19:10:00']);

  expect($log->searched_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($log->searched_at->format('H:i'))->toBe('19:10');
});
