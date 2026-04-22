<?php

namespace App\Domains\Core\Traits;

use App\Domains\Core\Services\CircuitBreakerService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait CircuitBreakerAware
{
  abstract protected function circuitServiceName(): string;

  protected function runThroughCircuitBreaker(callable $callback)
  {
    $service = $this->circuitServiceName();

    /** @var CircuitBreakerService $cb */
    $cb = app(CircuitBreakerService::class);

    // 1) هل مسموح بتنفيذ العملية؟
    if (!$cb->canProceed($service)) {
      throw new RuntimeException("Circuit is open for [{$service}]");
    }

    try {
      // 2) تنفيذ العملية
      $result = $callback();

      // 3) نجاح العملية
      $cb->reportSuccess($service);

      return $result;
    } catch (\Throwable $e) {

      if ($this->isValidationError($e)) {
        throw $e;
      }

      // 4) تسجيل الفشل
      $cb->reportFailure($service);

      throw $e;
    }
  }

  private function isValidationError(\Throwable $e): bool
  {
    // Laravel يرمي 422 كـ HttpException أو ValidationException
    if ($e instanceof HttpException && $e->getStatusCode() === 422) {
      return true;
    }

    // ValidationException
    if ($e instanceof \Illuminate\Validation\ValidationException) {
      return true;
    }

    return false;
  }
}
