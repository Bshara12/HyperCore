<?php

namespace Tests\Feature\Domains\Notifications\Jobs\Concerns;

use Tests\TestCase;
use App\Domains\Notifications\Jobs\Concerns\HasNotificationJobMiddleware;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\Middleware\ThrottlesExceptions;

class HasNotificationJobMiddlewareTest extends TestCase
{
  // --------------------------------------------------------------------
  // 1. اختبار مصفوفة الـ Middleware الافتراضية والتأكد من إعداداتها
  // --------------------------------------------------------------------
  public function test_it_returns_correct_middleware_array_with_default_configurations()
  {
    $jobWithTrait = new class {
      use HasNotificationJobMiddleware;
    };

    $middlewareArray = $jobWithTrait->middleware();

    $this->assertCount(2, $middlewareArray);
    $this->assertInstanceOf(WithoutOverlapping::class, $middlewareArray[0]);
    $this->assertInstanceOf(ThrottlesExceptions::class, $middlewareArray[1]);

    // نتحقق من الـ Key والـ Release بشكل آمن عبر الـ Reflection المضمون
    $overlapMiddleware = $middlewareArray[0];
    $reflectionOverlap = new \ReflectionClass($overlapMiddleware);

    $keyProperty = $reflectionOverlap->getProperty('key');
    $keyProperty->setAccessible(true);
    $this->assertEquals(get_class($jobWithTrait), $keyProperty->getValue($overlapMiddleware));

    $releaseProperty = $reflectionOverlap->getProperty('releaseAfter');
    $releaseProperty->setAccessible(true);
    $this->assertEquals(10, $releaseProperty->getValue($overlapMiddleware));
  }

  // --------------------------------------------------------------------
  // 2. اختبار إمكانية تخصيص وقراءة الدوال المحمية (Protected Fallbacks)
  // --------------------------------------------------------------------
  public function test_it_allows_reading_protected_configuration_fallback_methods()
  {
    $jobWithTrait = new class {
      use HasNotificationJobMiddleware;

      // نكشف الدوال هنا في الـ Anonymous class لغرض الفحص والتأكيد البرمجي
      public function getKey(): string
      {
        return $this->overlapKey();
      }
      public function getRelease(): int
      {
        return $this->overlapReleaseAfter();
      }
      public function getExpire(): int
      {
        return $this->overlapExpireAfter();
      }
      public function getMaxExceptions(): int
      {
        return $this->throttleMaxExceptions();
      }
      public function getDecay(): int
      {
        return $this->throttleDecayMinutes();
      }
    };

    $this->assertEquals(get_class($jobWithTrait), $jobWithTrait->getKey());
    $this->assertEquals(10, $jobWithTrait->getRelease());
    $this->assertEquals(120, $jobWithTrait->getExpire());
    $this->assertEquals(5, $jobWithTrait->getMaxExceptions());
    $this->assertEquals(5, $jobWithTrait->getDecay());
  }
}
