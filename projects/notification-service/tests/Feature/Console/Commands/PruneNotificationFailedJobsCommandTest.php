<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Mockery;

// تنظيف محاكاة Mockery بعد كل اختبار لتفادي تسريب الـ Mocks للاختبارات الأخرى
afterEach(function () {
  Mockery::close();
});

// --------------------------------------------------------------------
// الاختبار الأول: فحص التشغيل بالقيم الافتراضية (14 يوماً)
// --------------------------------------------------------------------
it('prunes failed jobs using default days option', function () {
  // تجميد الوقت الحالي لضمان تطابق حسابات الأيام بدقة ميكروثانية
  Carbon::setTestNow(now());

  $expectedBeforeDate = now()->subDays(14);

  // 1. إنشاء Mock لـ Queue Failer يحاكي تنفيذ دالة prune
  $failerMock = Mockery::mock('stdClass');
  $failerMock->shouldReceive('prune')
    ->once()
    ->with(Mockery::on(function ($date) use ($expectedBeforeDate) {
      // التأكد من أن التاريخ الممرر للدالة هو بالضبط الوقت الحالي ناقص 14 يوماً
      return $date->equalTo($expectedBeforeDate);
    }))
    ->andReturn(5); // نفترض أنه قام بحذف 5 وظائف فاشلة

  // 2. ربط الـ Mock داخل حاوية خدمات لارايفل بدلاً من الكائن الحقيقي
  $this->app->instance('queue.failer', $failerMock);

  // 3. تشغيل الـ Command وفحص المخرجات وكود النجاح
  $this->artisan('notifications:prune-failed')
    ->expectsOutput('Pruned failed jobs: 5')
    ->assertExitCode(Command::SUCCESS);

  // إلغاء تجميد الوقت
  Carbon::setTestNow();
});

// --------------------------------------------------------------------
// الاختبار الثاني: فحص تمرير قيمة مخصصة للـ Option (--days=30)
// --------------------------------------------------------------------
it('prunes failed jobs using custom days option', function () {
  Carbon::setTestNow(now());

  $expectedBeforeDate = now()->subDays(30);

  // 1. إنشاء الـ Mock وتوقع استقبال القيمة 30 يوماً
  $failerMock = Mockery::mock('stdClass');
  $failerMock->shouldReceive('prune')
    ->once()
    ->with(Mockery::on(function ($date) use ($expectedBeforeDate) {
      return $date->equalTo($expectedBeforeDate);
    }))
    ->andReturn(12); // نفترض حذف 12 وظيفة فاشلة

  $this->app->instance('queue.failer', $failerMock);

  // 2. تشغيل الـ Command مع تمرير حقل الأيام مخصصاً
  $this->artisan('notifications:prune-failed', ['--days' => 30])
    ->expectsOutput('Pruned failed jobs: 12')
    ->assertExitCode(Command::SUCCESS);

  Carbon::setTestNow();
});
