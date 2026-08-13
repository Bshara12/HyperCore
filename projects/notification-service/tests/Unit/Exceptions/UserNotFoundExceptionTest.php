<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\UserNotFoundException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function it_constructs_correct_error_message()
  {
    $userId = '12345';
    $exception = new UserNotFoundException($userId);

    // التحقق من أن رسالة الخطأ يتم إنشاؤها بالصيغة الصحيحة متضمنة معرّف المستخدم
    $this->assertEquals("User [12345] not found in auth service.", $exception->getMessage());
  }

  #[Test]
  public function it_extends_base_exception()
  {
    $exception = new UserNotFoundException('999');

    // التأكد من أنه يورث كلاس الـ Exception الأساسي في PHP
    $this->assertInstanceOf(\Exception::class, $exception);
  }

  #[Test]
  public function it_can_be_thrown_and_caught()
  {
    // اختبار إلقاء الـ Exception والالتقاط الناجح له مع رسالة الخطأ المتوقعة
    $this->expectException(UserNotFoundException::class);
    $this->expectExceptionMessage("User [777] not found in auth service.");

    throw new UserNotFoundException('777');
  }
}
