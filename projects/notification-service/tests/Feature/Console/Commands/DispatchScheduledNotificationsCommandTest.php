<?php

namespace Tests\Feature\Console\Commands;

use App\Domains\Notifications\Jobs\DispatchDueScheduledNotificationsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Console\Command;

// محاكاة كلاس الـ Job إذا لم يكن منشأً بالكامل في بيئة الاختبار الحالية لتفادي أخطاء الـ Namespace
if (!class_exists(DispatchDueScheduledNotificationsJob::class)) {
  class_alias(\stdClass::class, DispatchDueScheduledNotificationsJob::class);
}

it('dispatches the scheduled notifications job and outputs success message', function () {
  // 1. عمل Fake للـ Bus لمنع التنفيذ الحقيقي للـ Job ومراقبة استدعائه فقط
  Bus::fake();

  // 2. تشغيل الـ Command وفحص المخرجات وكود النجاح
  $this->artisan('notifications:dispatch-scheduled')
    ->expectsOutput('Scheduled notifications dispatch job queued.') // التحقق من النص المطبوع
    ->assertExitCode(Command::SUCCESS); // أو 0 للتأكد من حالة النجاح

  // 3. التأكد من أن الـ Job المحدد قد تم عمل dispatch له فعلاً بكفاءة
  Bus::assertDispatched(DispatchDueScheduledNotificationsJob::class);
});
