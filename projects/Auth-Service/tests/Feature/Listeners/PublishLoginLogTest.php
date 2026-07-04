<?php

namespace Tests\Feature\Listeners;

use App\Events\UserLoggedIn;
use App\Listeners\PublishLoginLog;
use App\Services\RabbitMQPublisher;
use Illuminate\Support\Str;
use Mockery;

test('it publishes login log to rabbitmq with correct payload structure', function () {
  // 1. تثبيت الوقت الحالي لضمان مطابقة حقل occurred_at بدقة متناهية
  $this->travelTo(now());
  $expectedTimestamp = now()->toDateTimeString();
  $testUserId = 99;

  // 2. عمل Mock لحدث تسجيل الدخول وتمرير الـ userId له
  $event = mock(UserLoggedIn::class);
  $event->userId = $testUserId;

  // 3. بناء الـ Mock الخاص بـ RabbitMQPublisher لمنع الاتصال الفعلي بالخادم
  $mockPublisher = mock(RabbitMQPublisher::class);

  $mockPublisher->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) use ($testUserId, $expectedTimestamp) {
      // التحقق السطري من صحة وبنية البيانات المرسلة للـ Queue
      return Str::isUuid($payload['event_id']) && // فحص أن المعرف فريد وبصيغة UUID صالحة
        $payload['module'] === 'auth' &&
        $payload['event_type'] === 'login' &&
        $payload['user_id'] === $testUserId &&
        $payload['occurred_at'] === $expectedTimestamp;
    }));

  // ربط كائن المحاكاة (Mock) داخل حاوية لارافل ليعود تلقائياً عند استدعاء app()
  $this->app->instance(RabbitMQPublisher::class, $mockPublisher);

  // 4. تشغيل الـ Listener يدوياً وتمرير الحدث المحاكي له
  $listener = new PublishLoginLog();
  $listener->handle($event);
});
