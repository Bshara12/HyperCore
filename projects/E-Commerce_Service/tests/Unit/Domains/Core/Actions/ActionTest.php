<?php

namespace Tests\Unit\Domains\Core\Actions;

use App\Domains\Core\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Facades\Validator;
use Exception;

// إنشاء كلاس وهمي لاختبار الـ Abstract Class
class TestAction extends Action
{
  protected function circuitServiceName(): string
  {
    return 'test-service';
  }

  public function execute(callable $callback)
  {
    return $this->run($callback);
  }

  // محاكاة الـ Trait لأنها غالباً تعتمد على Redis أو خدمة خارجية
  protected function runThroughCircuitBreaker(callable $callback)
  {
    return $callback();
  }
}

beforeEach(function () {
  $this->action = new TestAction();
});

it('executes the callback successfully inside a transaction', function () {
  DB::shouldReceive('transaction')->once()->andReturnUsing(fn($callback) => $callback());

  $result = $this->action->execute(fn() => 'success');

  expect($result)->toBe('success');
});

it('retries the operation on generic exception', function () {
  // نتوقع المحاولة 3 مرات (مرة أصلية + مرتين ريبلاي)
  DB::shouldReceive('transaction')
    ->times(3)
    ->andThrow(new Exception('Database Error'));

  expect(fn() => $this->action->execute(fn() => true))
    ->toThrow(Exception::class, 'Database Error');
});

it('does not retry and throws immediately on ValidationException', function () {
  $validator = Validator::make([], []);
  $exception = new ValidationException($validator);

  // يجب أن يتم استدعاء الترانزاكشن مرة واحدة فقط ثم يتوقف
  DB::shouldReceive('transaction')
    ->once()
    ->andThrow($exception);

  expect(fn() => $this->action->execute(fn() => true))
    ->toThrow(ValidationException::class);
});

it('does not retry on HttpException with status 422', function () {
  $exception = new HttpException(422, 'Unprocessable Entity');

  DB::shouldReceive('transaction')
    ->once()
    ->andThrow($exception);

  expect(fn() => $this->action->execute(fn() => true))
    ->toThrow(HttpException::class, 'Unprocessable Entity');
});

it('retries on other HttpExceptions (e.g., 500)', function () {
  $exception = new HttpException(500, 'Server Error');

  // سيعامل الـ 500 كخطأ عابر ويحاول 3 مرات
  DB::shouldReceive('transaction')
    ->times(3)
    ->andThrow($exception);

  expect(fn() => $this->action->execute(fn() => true))
    ->toThrow(Exception::class, 'Server Error');
});
