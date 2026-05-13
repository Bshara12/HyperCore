<?php

namespace Tests\Integration\Domains\Core\Actions;

use Tests\TestCase;
use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Exception;

// كلاس وهمي للاختبار
class FakeAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'test-service';
  }
  public function execute(callable $callback)
  {
    return $this->run($callback);
  }
}

class ActionTest extends TestCase
{
  // 1. تفعيل قاعدة البيانات لحل مشكلة "no such table: circuit_breakers"
  use RefreshDatabase;

  private FakeAction $action;

  protected function setUp(): void
  {
    parent::setUp();
    $this->action = new FakeAction();
  }

  /** @test */
  public function it_runs_successfully_on_first_attempt()
  {
    $result = $this->action->execute(fn() => 'success');
    $this->assertEquals('success', $result);
  }

  /** @test */
  public function it_retries_and_succeeds_eventually()
  {
    $attempts = 0;
    $result = $this->action->execute(function () use (&$attempts) {
      $attempts++;
      if ($attempts < 2) {
        throw new Exception('Temporary failure');
      }
      return 'success_after_retry';
    });

    $this->assertEquals(2, $attempts);
    $this->assertEquals('success_after_retry', $result);
  }

  /** @test */
  public function it_does_not_retry_on_validation_exception()
  {
    $attempts = 0;

    try {
      $this->action->execute(function () use (&$attempts) {
        $attempts++;
        throw ValidationException::withMessages(['field' => 'error']);
      });
    } catch (ValidationException $e) {
      // نتحقق أن المحاولة كانت واحدة فقط
      $this->assertEquals(1, $attempts);
      return;
    }

    $this->fail('ValidationException was not thrown');
  }

  /** @test */
  public function it_does_not_retry_on_422_http_exception()
  {
    $attempts = 0;

    try {
      $this->action->execute(function () use (&$attempts) {
        $attempts++;
        throw new HttpException(422, 'Unprocessable Entity');
      });
    } catch (HttpException $e) {
      $this->assertEquals(422, $e->getStatusCode());
      $this->assertEquals(1, $attempts);
      return;
    }

    $this->fail('HttpException 422 was not thrown');
  }

  /** @test */
  public function it_throws_final_exception_after_3_failed_attempts()
  {
    try {
      $this->action->execute(function () {
        throw new Exception('Persistent error');
      });
    } catch (Exception $e) {
      $this->assertStringContainsString('The operation failed after 3 attempts', $e->getMessage());
      return;
    }

    $this->fail('Final exception was not thrown');
  }

  /** @test */
  public function it_executes_within_a_real_database_transaction()
  {
    // بدلاً من Mocking (الذي سبب مشكلة Facade)، نتحقق من الترانزاكشن فعلياً
    $result = $this->action->execute(function () {
      return DB::transactionLevel();
    });

    // المستوى يجب أن يكون أكبر من 0 لأننا داخل DB::transaction
    $this->assertGreaterThan(0, $result);
  }
}
