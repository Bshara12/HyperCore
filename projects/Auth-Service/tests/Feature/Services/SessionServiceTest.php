<?php

namespace Tests\Feature\Services;

use App\Services\SessionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;

beforeEach(function () {
  $this->sessionService = new SessionService();
});

// =========================================================================
// 1. اختبارات دالة: create واختبار الـ Device Detection (تغطية غير مباشرة)
// =========================================================================

test('create method stores user session correctly and returns a valid ULID', function () {
  $userId = 'user-ulid-12345';
  $ip = '192.168.1.1';
  $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'; // Windows Agent

  DB::shouldReceive('table')
    ->once()
    ->with('my_sessions')
    ->andReturn(Mockery::self());

  DB::shouldReceive('insert')
    ->once()
    ->with(Mockery::on(function ($argument) use ($userId, $ip, $userAgent) {
      return Str::isUlid($argument['id']) &&
        $argument['user_id'] === $userId &&
        $argument['ip_address'] === $ip &&
        $argument['user_agent'] === $userAgent &&
        $argument['device_name'] === 'Windows device' &&
        isset($argument['last_activity_at']) &&
        isset($argument['expires_at']);
    }))
    ->andReturn(true);

  $sessionId = $this->sessionService->create($userId, $ip, $userAgent);

  expect($sessionId)->toBeString();
  expect(Str::isUlid($sessionId))->toBeTrue();
});

// فحص تمييز الأجهزة المختلفة بناءً على الـ User Agent لضمان تغطية الدالة الخاصة 100%
dataset('user_agents_fixtures', [
  ['Mozilla/5.0 (Macintosh; Intel Mac OS X)', 'Mac device'],
  ['Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)', 'iPhone'],
  ['Mozilla/5.0 (Linux; Android 12; SM-S908B)', 'Android device'],
  ['Mozilla/5.0 (X11; Linux x86_64)', 'Linux device'],
  ['Mozilla/5.0 (Custom Secret Browser)', 'Browser device'],
  [null, 'Unknown device'],
]);

test('create method detects correct device name based on user agent', function (?string $userAgent, string $expectedDevice) {
  DB::shouldReceive('table')->once()->with('my_sessions')->andReturn(Mockery::self());

  DB::shouldReceive('insert')
    ->once()
    ->with(Mockery::on(function ($argument) use ($expectedDevice) {
      return $argument['device_name'] === $expectedDevice;
    }))
    ->andReturn(true);

  $this->sessionService->create('user-id', '127.0.0.1', $userAgent);
})->with('user_agents_fixtures');


// =========================================================================
// 2. اختبار دالة: createServiceSession
// =========================================================================

test('createServiceSession stores service session tracking details correctly', function () {
  $clientId = 'microservice-cms-id';
  $serviceClientId = 77;

  DB::shouldReceive('table')
    ->once()
    ->with('service_sessions')
    ->andReturn(Mockery::self());

  DB::shouldReceive('insert')
    ->once()
    ->with(Mockery::on(function ($argument) use ($clientId, $serviceClientId) {
      return Str::isUlid($argument['id']) &&
        $argument['client_id'] === $clientId &&
        $argument['service_client_id'] === $serviceClientId &&
        isset($argument['last_activity_at']) &&
        isset($argument['expires_at']);
    }))
    ->andReturn(true);

  $sessionId = $this->sessionService->createServiceSession($clientId, $serviceClientId);

  expect($sessionId)->toBeString();
  expect(Str::isUlid($sessionId))->toBeTrue();
});
