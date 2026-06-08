<?php

namespace Tests\Unit\Domains\Core\Actions;

use App\Domains\Core\Actions\Action;
use App\Domains\Core\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Exception;

uses(RefreshDatabase::class);

beforeEach(function () {
  // 1. محاكاة خدمة الـ Circuit Breaker لأن الكلاس يستخدم trait يحتاجها
  $this->mock(CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportSuccess')->andReturn(true)->byDefault();
    $mock->shouldReceive('reportFailure')->andReturn(true)->byDefault();
  });

  // 2. إنشاء كلاس مجهول يرث من Action لاختباره
  $this->action = new class extends Action {
    protected function circuitServiceName(): string
    {
      return 'test.service';
    }

    // دالة مساعدة لاستدعاء الـ run المحمية
    public function runPublicly(callable $callback)
    {
      return $this->run($callback);
    }
  };
});

test('it executes callback successfully', function () {
  $result = $this->action->runPublicly(fn() => 'success');
  expect($result)->toBe('success');
});

test('it throws ValidationException immediately without retry', function () {
  $attempts = 0;

  // استخدم try-catch بدلاً من toThrow لضمان تحديث العداد
  try {
    $this->action->runPublicly(function () use (&$attempts) {
      $attempts++;
      throw ValidationException::withMessages(['error' => 'validation failed']);
    });
  } catch (ValidationException $e) {
    // إذا وصل الكود هنا، فهذا يعني أن الاستثناء تم رميه بنجاح
    expect(true)->toBeTrue();
  }

  // التأكد أن الـ retry لم يعمل (تمت المحاولة مرة واحدة فقط)
  expect($attempts)->toBe(1);
});

test('it throws HttpException 422 immediately without retry', function () {
  $attempts = 0;

  try {
    $this->action->runPublicly(function () use (&$attempts) {
      $attempts++;
      abort(422, 'Unprocessable Entity');
    });
  } catch (HttpException $e) {
    expect($e->getStatusCode())->toBe(422);
  }

  expect($attempts)->toBe(1);
});

test('it retries 3 times on generic exceptions then wraps and throws', function () {
  $attempts = 0;

  // محاولة تنفيذ كود يفشل دائماً
  $exception = null;
  try {
    $this->action->runPublicly(function () use (&$attempts) {
      $attempts++;
      throw new Exception('Connection failed');
    });
  } catch (Exception $e) {
    $exception = $e;
  }

  // التأكد من أن الكود حاول 3 مرات
  expect($attempts)->toBe(3);

  // التأكد من أن الخطأ تم تغليفه بالرسالة المطلوبة
  expect($exception)->toBeInstanceOf(Exception::class)
    ->and($exception->getMessage())->toContain('The operation failed after 3 attempts');
});
