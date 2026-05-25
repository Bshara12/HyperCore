<?php

use App\Domains\Search\Models\UserClickLog;
use App\Domains\Search\Models\UserSearchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it belongs to search log', function () {
  $searchLog = UserSearchLog::factory()->create();
  $clickLog = UserClickLog::factory()->create(['search_log_id' => $searchLog->id]);

  expect($clickLog->searchLog)->toBeInstanceOf(UserSearchLog::class)
    ->and($clickLog->searchLog->id)->toBe($searchLog->id);
});

test('it casts clicked_at to datetime', function () {
  $clickLog = UserClickLog::factory()->create(['clicked_at' => '2026-05-24 19:00:00']);

  expect($clickLog->clicked_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class)
    ->and($clickLog->clicked_at->format('Y-m-d'))->toBe('2026-05-24');
});
