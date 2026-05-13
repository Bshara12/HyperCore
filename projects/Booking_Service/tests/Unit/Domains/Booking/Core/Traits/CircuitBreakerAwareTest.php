<?php

namespace Tests\Integration\Domains\Core\Traits;

use Tests\TestCase;
use App\Domains\Core\Traits\CircuitBreakerAware;
use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Exception;

class CircuitBreakerAwareTest extends TestCase
{
  use RefreshDatabase;

  private $testClass;
  private $serviceName = 'test-trait-service';

  protected function setUp(): void
  {
    parent::setUp();

    // إنشاء كلاس وهمي يطبق الـ Trait للاختبار
    $this->testClass = new class($this->serviceName) {
      use CircuitBreakerAware;
      private $name;
      public function __construct($name)
      {
        $this->name = $name;
      }
      protected function circuitServiceName(): string
      {
        return $this->name;
      }
      public function execute(callable $callback)
      {
        return $this->runThroughCircuitBreaker($callback);
      }
    };
  }

  /** @test */
  public function it_reports_success_and_returns_result()
  {
    $result = $this->testClass->execute(fn() => 'done');

    $this->assertEquals('done', $result);
    // نتحقق أن السجل حُذف (سلوك reportSuccess)
    $this->assertDatabaseMissing('circuit_breakers', ['service_name' => $this->serviceName]);
  }

  /** @test */
  public function it_throws_runtime_exception_if_circuit_is_open()
  {
    // إعداد الحالة كـ open في قاعدة البيانات
    app(CircuitBreakerService::class)->reportFailure($this->serviceName); // failure 1...5
    for ($i = 0; $i < 5; $i++) {
      app(CircuitBreakerService::class)->reportFailure($this->serviceName);
    }

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage("Circuit is open for [{$this->serviceName}]");

    $this->testClass->execute(fn() => 'won’t run');
  }

  /** @test */
  public function it_reports_failure_on_general_exception()
  {
    try {
      $this->testClass->execute(function () {
        throw new Exception('Logic Error');
      });
    } catch (Exception $e) {
      // التحقق من تسجيل الفشل في قاعدة البيانات
      $this->assertDatabaseHas('circuit_breakers', [
        'service_name' => $this->serviceName,
        'failure_count' => 1
      ]);
      return;
    }

    $this->fail('Exception was not thrown');
  }

  /** @test */
  public function it_does_not_report_failure_on_validation_exception()
  {
    try {
      $this->testClass->execute(function () {
        throw ValidationException::withMessages(['email' => 'invalid']);
      });
    } catch (ValidationException $e) {
      // التحقق أن السجل لم يُنشأ أو لم يتأثر (لأننا تجاوزنا reportFailure)
      // بما أن الـ getOrCreate يُستدعى في بداية canProceed، السجل سيكون موجوداً بـ 0 إخفاقات
      $this->assertDatabaseHas('circuit_breakers', [
        'service_name' => $this->serviceName,
        'failure_count' => 0
      ]);
      return;
    }

    $this->fail('ValidationException was not thrown');
  }

  /** @test */
  public function it_does_not_report_failure_on_422_http_exception()
  {
    try {
      $this->testClass->execute(function () {
        throw new HttpException(422, 'Validation Error');
      });
    } catch (HttpException $e) {
      $this->assertDatabaseHas('circuit_breakers', [
        'service_name' => $this->serviceName,
        'failure_count' => 0
      ]);
      return;
    }

    $this->fail('HttpException was not thrown');
  }
}
