<?php

namespace Tests\Unit\Domains\Core\Services;

use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->service = new CircuitBreakerService();
});

test('it creates a record when calling canProceed for the first time', function () {
  $result = $this->service->canProceed('test-service');

  expect($result)->toBeTrue();
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => 'test-service',
    'state' => 'closed'
  ]);
});

test('it transitions to open state when failure threshold is reached', function () {
  $serviceName = 'test-service';

  // نبلغ عن 5 فشل (حسب الكود threshold هو 5)
  for ($i = 0; $i < 5; $i++) {
    $this->service->reportFailure($serviceName);
  }

  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $serviceName,
    'state' => 'open'
  ]);
});

test('it returns false when state is open and cooldown is not finished', function () {
  $serviceName = 'test-service';

  // إعداد الحالة كـ open وتاريخ مستقبلي
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'open',
    'next_attempt_at' => now()->addMinutes(10),
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $result = $this->service->canProceed($serviceName);
  expect($result)->toBeFalse();
});

test('it transitions to half-open when open cooldown has passed', function () {
  $serviceName = 'test-service';

  // إعداد الحالة كـ open وتاريخ ماضي
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'open',
    'next_attempt_at' => now()->subMinutes(1), // وقت ماضي
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $result = $this->service->canProceed($serviceName);

  expect($result)->toBeTrue();
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $serviceName,
    'state' => 'half-open'
  ]);
});

test('it transitions back to open if failure reported while half-open', function () {
  $serviceName = 'test-service';

  // إعداد الحالة كـ half-open
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'half-open',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $this->service->reportFailure($serviceName);

  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $serviceName,
    'state' => 'open'
  ]);
});

test('it deletes record when reporting success', function () {
  $serviceName = 'test-service';

  // إضافة سجل
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'closed',
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  $this->service->reportSuccess($serviceName);

  $this->assertDatabaseMissing('circuit_breakers', [
    'service_name' => $serviceName
  ]);
});

test('it returns true when state is half-open in canProceed', function () {
  $serviceName = 'half-open-service';

  // إدخال الحالة يدوياً كـ half-open
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'half-open',
    'failure_count' => 0,
    'failure_threshold' => 5,
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // تنفيذ التابع والتأكد أنه سيعيد true
  $result = $this->service->canProceed($serviceName);
  expect($result)->toBeTrue();
});
test('it returns early without doing anything when reporting failure for an already open service', function () {
  $serviceName = 'already-open-service';

  // إدخال الحالة يدوياً كـ open مع عدد فشل معين
  DB::table('circuit_breakers')->insert([
    'service_name' => $serviceName,
    'state' => 'open',
    'failure_count' => 10, // لنفترض أن الفشل 10
    'failure_threshold' => 5,
    'created_at' => now(),
    'updated_at' => now(),
  ]);

  // استدعاء التابع (يجب أن ينتهي فوراً ولا يغير أي شيء)
  $this->service->reportFailure($serviceName);

  // التأكد أن البيانات لم تتغير (لا زال الفشل 10)
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $serviceName,
    'state' => 'open',
    'failure_count' => 10,
  ]);
});
