<?php

namespace App\Domains\Core\Actions;

use App\Domains\Core\Traits\CircuitBreakerAware;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class Action
{
  use CircuitBreakerAware;

  abstract protected function circuitServiceName(): string;

  protected function run(callable $callback)
  {
    return $this->runThroughCircuitBreaker(function () use ($callback) {

      try {

        return retry(
          3,
          function () use ($callback) {
            return DB::transaction(function () use ($callback) {
              return $callback();
            });
          },
          100,
          function ($exception) {

            if ($exception instanceof ValidationException) {
              return false;
            }

            if ($exception instanceof HttpException && $exception->getStatusCode() === 422) {
              return false;
            }

            return true;
          }
        );
      } catch (Exception $e) {

        if (
          $e instanceof ValidationException ||
          ($e instanceof HttpException && $e->getStatusCode() === 422)
        ) {
          throw $e;
        }

        throw new Exception(
          "The operation failed after 3 attempts: " . $e->getMessage(),
          $e->getCode(),
          $e
        );
      }
    });
  }
}
