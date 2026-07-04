<?php

namespace Tests\Unit\Models;

use App\Models\ServiceClient;
use App\Models\ServiceSession;
use Tests\TestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
  Schema::dropIfExists('service_sessions');
  Schema::dropIfExists('service_clients');

  Schema::create('service_clients', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('client_id');
    $table->string('client_secret');
    $table->timestamps();
  });

  Schema::create('service_sessions', function (Blueprint $table) {
    $table->char('id', 26)->primary();
    $table->foreignId('service_client_id')->constrained('service_clients')->onDelete('cascade');
    $table->string('client_id')->nullable();
    $table->dateTime('last_activity_at')->nullable();
    $table->dateTime('expires_at');
    $table->dateTime('revoked_at')->nullable();
    $table->timestamps();
  });
});

// ─── الفحوصات ─────────────────────────────────────────────────────────────

test('it automatically generates a ULID when creating a session without an id', function () {
  $client = ServiceClient::create([
    'name'          => 'App',
    'client_id'     => 'id_1',
    'client_secret' => 'secret_1',
  ]);

  $session = ServiceSession::create([
    'service_client_id' => $client->id,
    'client_id'         => 'client_xyz',
    'expires_at'        => now()->addDays(1),
  ]);

  expect($session->id)->not->toBeNull();
  expect(strlen($session->id))->toBe(26);
});

test('it does not overwrite the id if a custom id is provided during creation', function () {
  $client = ServiceClient::create([
    'name'          => 'App',
    'client_id'     => 'id_2',
    'client_secret' => 'secret_2',
  ]);

  $customId = '01KTC6TFSGZFGP5KWYY9TG9XXS';

  $session = ServiceSession::create([
    'id'                => $customId,
    'service_client_id' => $client->id,
    'client_id'         => 'client_abc',
    'expires_at'        => now()->addDays(1),
  ]);

  expect($session->id)->toBe($customId);
});

test('it correctly casts date fields to Carbon datetime instances', function () {
  $client = ServiceClient::create([
    'name'          => 'App',
    'client_id'     => 'id_3',
    'client_secret' => 'secret_3',
  ]);

  $session = ServiceSession::create([
    'service_client_id' => $client->id,
    'expires_at'        => '2026-06-15 12:00:00',
    'last_activity_at'  => '2026-06-05 15:00:00',
    'revoked_at'        => '2026-06-06 09:00:00',
  ]);

  $freshSession = ServiceSession::find($session->id);

  expect($freshSession->expires_at)->toBeInstanceOf(Carbon::class);
  expect($freshSession->last_activity_at)->toBeInstanceOf(Carbon::class);
  expect($freshSession->revoked_at)->toBeInstanceOf(Carbon::class);
  expect($freshSession->expires_at->format('Y-m-d H:i:s'))->toBe('2026-06-15 12:00:00');
});
