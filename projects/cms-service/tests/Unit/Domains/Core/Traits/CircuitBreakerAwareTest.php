<?php

namespace Tests\Unit\Domains\Core\Traits;

use App\Domains\Core\Services\CircuitBreakerService;
use App\Domains\Core\Traits\CircuitBreakerAware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use RuntimeException;
use Exception;

// إنشاء كلاس مجهول لاستخدامه في الاختبار
class TestCircuitBreakerClass
{
  use CircuitBreakerAware;

  protected function circuitServiceName(): string
  {
    return 'test-service';
  }

  public function runTest(callable $callback)
  {
    return $this->runThroughCircuitBreaker($callback);
  }
}

beforeEach(function () {
  $this->instance = new TestCircuitBreakerClass();
});

test('it throws RuntimeException when circuit is open', function () {
  // محاكاة السيرفس بحيث يرفض التنفيذ (canProceed returns false)
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->once()->with('test-service')->andReturn(false);
  });

  expect(fn() => $this->instance->runTest(fn() => 'should not run'))
    ->toThrow(RuntimeException::class, 'Circuit is open for [test-service]');
});

test('it executes callback and reports success when circuit is closed', function () {
  // محاكاة السيرفس بحيث يسمح بالتنفيذ
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->once()->andReturn(true);
    $mock->shouldReceive('reportSuccess')->once()->with('test-service');
  });

  $result = $this->instance->runTest(fn() => 'success');
  expect($result)->toBe('success');
});

test('it reports failure when callback throws generic exception', function () {
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->once()->andReturn(true);
    $mock->shouldReceive('reportFailure')->once()->with('test-service');
  });

  expect(fn() => $this->instance->runTest(function () {
    throw new Exception('Something went wrong');
  }))->toThrow(Exception::class);
});

test('it does not report failure when callback throws ValidationException', function () {
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->once()->andReturn(true);
    // لا يجب استدعاء reportFailure هنا
    $mock->shouldNotReceive('reportFailure');
  });

  expect(fn() => $this->instance->runTest(function () {
    throw ValidationException::withMessages(['field' => 'error']);
  }))->toThrow(ValidationException::class);
});

test('it does not report failure when callback throws 422 HttpException', function () {
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->once()->andReturn(true);
    $mock->shouldNotReceive('reportFailure');
  });

  expect(fn() => $this->instance->runTest(function () {
    throw new HttpException(422, 'Unprocessable Entity');
  }))->toThrow(HttpException::class);
});
