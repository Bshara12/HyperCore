<?php

namespace Tests\Unit\Domains\Core\Traits;

use App\Domains\Core\Traits\CircuitBreakerAware;
use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Validator;
use Mockery;
use RuntimeException;
use Exception;

// إنشاء كلاس وهمي لاستخدام الـ Trait
class CircuitBreakerTraitUser
{
  use CircuitBreakerAware;

  protected function circuitServiceName(): string
  {
    return 'test-service';
  }

  public function execute(callable $callback)
  {
    return $this->runThroughCircuitBreaker($callback);
  }
}

beforeEach(function () {
  // عمل Mock للخدمة التي تعتمد عليها الـ Trait
  $this->cbService = Mockery::mock(CircuitBreakerService::class);
  // تسجيل الـ Mock في حاوية لارافل (Service Container)
  app()->instance(CircuitBreakerService::class, $this->cbService);

  $this->traitUser = new CircuitBreakerTraitUser();
});

afterEach(function () {
  Mockery::close();
});

it('throws RuntimeException when circuit is open', function () {
  $this->cbService->shouldReceive('canProceed')
    ->once()
    ->with('test-service')
    ->andReturn(false);

  expect(fn() => $this->traitUser->execute(fn() => 'work'))
    ->toThrow(RuntimeException::class, "Circuit is open for [test-service]");
});

it('reports success when callback executes successfully', function () {
  $this->cbService->shouldReceive('canProceed')->once()->andReturn(true);

  // يجب استدعاء reportSuccess عند النجاح
  $this->cbService->shouldReceive('reportSuccess')
    ->once()
    ->with('test-service');

  $result = $this->traitUser->execute(fn() => 'done');

  expect($result)->toBe('done');
});

it('reports failure and rethrows when a generic exception occurs', function () {
  $this->cbService->shouldReceive('canProceed')->once()->andReturn(true);

  // يجب استدعاء reportFailure عند حدوث خطأ عام
  $this->cbService->shouldReceive('reportFailure')
    ->once()
    ->with('test-service');

  expect(fn() => $this->traitUser->execute(fn() => throw new Exception('API Error')))
    ->toThrow(Exception::class, 'API Error');
});

it('does not report failure on ValidationException', function () {
  $this->cbService->shouldReceive('canProceed')->once()->andReturn(true);

  // لا يجب استدعاء reportFailure في أخطاء التحقق
  $this->cbService->shouldNotReceive('reportFailure');

  $validator = Validator::make([], []);
  $exception = new ValidationException($validator);

  expect(fn() => $this->traitUser->execute(fn() => throw $exception))
    ->toThrow(ValidationException::class);
});

it('does not report failure on 422 HttpException', function () {
  $this->cbService->shouldReceive('canProceed')->once()->andReturn(true);

  // لا يجب استدعاء reportFailure في خطأ 422
  $this->cbService->shouldNotReceive('reportFailure');

  $exception = new HttpException(422, 'Validation Failed');

  expect(fn() => $this->traitUser->execute(fn() => throw $exception))
    ->toThrow(HttpException::class);
});

it('reports failure on other HttpExceptions (e.g., 500)', function () {
  $this->cbService->shouldReceive('canProceed')->once()->andReturn(true);

  // يجب تسجيل الفشل إذا كان الخطأ ليس 422
  $this->cbService->shouldReceive('reportFailure')->once()->with('test-service');

  $exception = new HttpException(500, 'Server Error');

  expect(fn() => $this->traitUser->execute(fn() => throw $exception))
    ->toThrow(HttpException::class);
});
