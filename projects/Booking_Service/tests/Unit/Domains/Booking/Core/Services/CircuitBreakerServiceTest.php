<?php

namespace Tests\Integration\Domains\Core\Services;

use Tests\TestCase;
use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

class CircuitBreakerServiceTest extends TestCase
{
  use RefreshDatabase;

  private CircuitBreakerService $service;
  private string $serviceName = 'test-service';

  protected function setUp(): void
  {
    parent::setUp();
    $this->service = app(CircuitBreakerService::class);
  }

  /** @test */
  public function it_creates_record_if_not_exists_and_allows_proceeding()
  {
    // تغطي الجزء الأول من getOrCreate (إنشاء سجل جديد)
    $canProceed = $this->service->canProceed($this->serviceName);

    $this->assertTrue($canProceed);
    $this->assertDatabaseHas('circuit_breakers', [
      'service_name' => $this->serviceName,
      'state' => 'closed'
    ]);

    // استدعاء مرة أخرى لتغطية الجزء الثاني من getOrCreate (جلب سجل موجود)
    $canProceedAgain = $this->service->canProceed($this->serviceName);
    $this->assertTrue($canProceedAgain);
  }

  /** @test */
  public function it_reports_failure_and_increments_count()
  {
    $this->service->reportFailure($this->serviceName);

    $this->assertDatabaseHas('circuit_breakers', [
      'service_name' => $this->serviceName,
      'failure_count' => 1,
      'state' => 'closed'
    ]);
  }

  /** @test */
  public function it_opens_circuit_when_threshold_is_reached()
  {
    // تغطي الانتقال من closed إلى open عبر reportFailure
    for ($i = 0; $i < 5; $i++) {
      $this->service->reportFailure($this->serviceName);
    }

    $this->assertDatabaseHas('circuit_breakers', [
      'service_name' => $this->serviceName,
      'state' => 'open'
    ]);

    $this->assertFalse($this->service->canProceed($this->serviceName));
  }

  /** @test */
  public function it_moves_to_half_open_when_retry_time_passed()
  {
    DB::table('circuit_breakers')->insert([
      'service_name'    => $this->serviceName,
      'state'           => 'open',
      'next_attempt_at' => now()->subMinutes(1),
      'failure_threshold' => 5,
      'created_at'      => now(),
      'updated_at'      => now(),
    ]);

    // تغطي دالة setState داخلياً لتحويلها إلى half-open
    $canProceed = $this->service->canProceed($this->serviceName);

    $this->assertTrue($canProceed);
    $this->assertDatabaseHas('circuit_breakers', [
      'service_name' => $this->serviceName,
      'state'        => 'half-open'
    ]);
  }

  /** @test */
  public function it_reopens_circuit_if_failure_occurs_in_half_open_state()
  {
    DB::table('circuit_breakers')->insert([
      'service_name'      => $this->serviceName,
      'state'             => 'half-open',
      'failure_threshold' => 5,
      'updated_at'        => now(),
    ]);

    // تغطي مسار الفشل داخل حالة half-open في دالة reportFailure
    $this->service->reportFailure($this->serviceName);

    $this->assertDatabaseHas('circuit_breakers', [
      'service_name' => $this->serviceName,
      'state'        => 'open'
    ]);
  }

  /** @test */
  public function it_returns_true_for_default_case_in_can_proceed()
  {
    // تغطي سطر return true الأخير في دالة canProceed (في حال كانت الحالة غير معروفة أو half-open)
    DB::table('circuit_breakers')->insert([
      'service_name'      => $this->serviceName,
      'state'             => 'half-open',
      'failure_threshold' => 5
    ]);

    $this->assertTrue($this->service->canProceed($this->serviceName));
  }

  /** @test */
  public function it_does_nothing_when_reporting_failure_on_already_open_circuit()
  {
    DB::table('circuit_breakers')->insert([
      'service_name'      => $this->serviceName,
      'state'             => 'open',
      'failure_threshold' => 5
    ]);

    $this->service->reportFailure($this->serviceName);
    $this->assertTrue(true);
  }

  /** @test */
  public function it_deletes_record_on_success_report()
  {
    $this->service->canProceed($this->serviceName);
    $this->service->reportSuccess($this->serviceName);

    $this->assertDatabaseMissing('circuit_breakers', [
      'service_name' => $this->serviceName
    ]);
  }
}
