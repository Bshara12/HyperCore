<?php

namespace Tests\Unit\Domains\Core\Services;

use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // لضمان نظافة قاعدة البيانات في كل اختبار

beforeEach(function () {
  $this->service = new CircuitBreakerService();
  $this->serviceName = 'test-api-service';
});

/**
 * اختبار حالة الـ Closed (الحالة الطبيعية)
 */
it('can proceed when the state is closed', function () {
  $result = $this->service->canProceed($this->serviceName);

  expect($result)->toBeTrue();
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $this->serviceName,
    'state' => 'closed'
  ]);
});

/**
 * اختبار التحول إلى حالة الـ Open عند الوصول للحد الأقصى من الفشل
 */
it('opens the circuit when failure threshold is reached', function () {
  // نفترض أن الحد هو 5 فشلات
  for ($i = 0; $i < 5; $i++) {
    $this->service->reportFailure($this->serviceName);
  }

  $cb = DB::table('circuit_breakers')->where('service_name', $this->serviceName)->first();

  expect($cb->state)->toBe('open')
    ->and($cb->failure_count)->toBe(5)
    ->and($cb->next_attempt_at)->not->toBeNull();

  // التأكد من أنه لا يمكن المتابعة الآن
  expect($this->service->canProceed($this->serviceName))->toBeFalse();
});

/**
 * اختبار الانتقال من Open إلى Half-Open بعد مرور الوقت المحدد
 */
it('transitions to half-open after the timeout expires', function () {
  // فتح الدائرة يدوياً
  $this->service->reportFailure($this->serviceName);
  DB::table('circuit_breakers')->where('service_name', $this->serviceName)->update([
    'state' => 'open',
    'failure_threshold' => 1,
    'next_attempt_at' => now()->addMinutes(5)
  ]);

  // الآن هي مفتوحة، لا يمكن المتابعة
  expect($this->service->canProceed($this->serviceName))->toBeFalse();

  // التلاعب بالوقت: السفر للمستقبل (6 دقائق)
  Carbon::setTestNow(now()->addMinutes(6));

  // الآن يجب أن يسمح بالمتابعة ويحولها لـ half-open
  expect($this->service->canProceed($this->serviceName))->toBeTrue();
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $this->serviceName,
    'state' => 'half-open'
  ]);

  Carbon::setTestNow(); // إعادة الوقت لحالته الطبيعية
});

/**
 * اختبار الفشل أثناء حالة الـ Half-Open (إعادة الفتح فوراً)
 */
it('re-opens the circuit immediately if it fails in half-open state', function () {
  // وضع الدائرة في حالة half-open
  DB::table('circuit_breakers')->insert([
    'service_name' => $this->serviceName,
    'state' => 'half-open',
    'failure_count' => 5,
    'failure_threshold' => 5,
    'created_at' => now(),
    'updated_at' => now()
  ]);

  $this->service->reportFailure($this->serviceName);

  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $this->serviceName,
    'state' => 'open'
  ]);
});

/**
 * اختبار النجاح (reportSuccess) الذي يجب أن يحذف السجل تماماً
 */
it('removes the circuit breaker record upon success', function () {
  $this->service->reportFailure($this->serviceName); // إنشاء سجل

  $this->service->reportSuccess($this->serviceName);

  $this->assertDatabaseMissing('circuit_breakers', [
    'service_name' => $this->serviceName
  ]);
});

/**
 * اختبار زيادة عداد الفشل بدون الوصول للحد الأقصى
 */
it('increments failure count but stays closed if below threshold', function () {
  $this->service->reportFailure($this->serviceName);

  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $this->serviceName,
    'state' => 'closed',
    'failure_count' => 1
  ]);
});

/**
 * اختبار أن canProceed تعيد true عندما تكون الحالة half-open
 * هذا يغطي سطر الـ return true الأخير في التابع
 */
it('returns true when state is half-open', function () {
  // إدخال سجل بحالة half-open يدوياً
  DB::table('circuit_breakers')->insert([
    'service_name' => $this->serviceName,
    'state' => 'half-open',
    'failure_count' => 0,
    'failure_threshold' => 5,
    'created_at' => now(),
    'updated_at' => now()
  ]);

  $result = $this->service->canProceed($this->serviceName);

  expect($result)->toBeTrue();
});

/**
 * اختبار أن reportFailure لا تفعل شيئاً إذا كانت الحالة open بالفعل
 * هذا يغطي شرط الـ if ($cb->state === 'open') { return; }
 */
it('does nothing in reportFailure if state is already open', function () {
  // إعداد الدائرة لتكون مفتوحة مع وقت محدد
  $fixedTime = now();
  DB::table('circuit_breakers')->insert([
    'service_name' => $this->serviceName,
    'state' => 'open',
    'failure_count' => 5,
    'failure_threshold' => 5,
    'next_attempt_at' => $fixedTime->addMinutes(5),
    'updated_at' => $fixedTime,
    'created_at' => $fixedTime
  ]);

  // استدعاء التابع
  $this->service->reportFailure($this->serviceName);

  // التحقق من أن البيانات لم تتغير (لم يتم تحديث الـ updated_at أو أي حقل آخر)
  $this->assertDatabaseHas('circuit_breakers', [
    'service_name' => $this->serviceName,
    'state' => 'open',
    'updated_at' => $fixedTime // التأكد أن الوقت لم يتغير، مما يعني أنه لم يحدث Update
  ]);
});
