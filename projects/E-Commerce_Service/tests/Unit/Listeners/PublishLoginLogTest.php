<?php

namespace Tests\Unit\Listeners;

use App\Events\UserLoggedIn;
use App\Listeners\PublishLoginLog;
use App\Domains\Core\Services\RabbitMQPublisher;
use Illuminate\Support\Facades\Date;
use Mockery;

afterEach(function () {
  Mockery::close();
});

it('publishes a login log to rabbitmq when user logs in', function () {
  // 1. تثبيت الوقت لضمان دقة التحقق من التوقيت (Time Testing)
  Date::setTestNow(now());
  $currentTime = now()->toDateTimeString();

  // 2. محاكاة الـ RabbitMQPublisher
  $publisherMock = Mockery::mock(RabbitMQPublisher::class);

  // التوقع: يجب استدعاء publish مرة واحدة بمصفوفة تحتوي على البيانات الصحيحة
  $publisherMock->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) use ($currentTime) {
      return $payload['module'] === 'auth' &&
        $payload['event_type'] === 'login' &&
        $payload['user_id'] === 123 &&
        $payload['occurred_at'] === $currentTime &&
        isset($payload['event_id']); // التأكد من توليد الـ UUID
    }));

  // حقن الـ Mock في الحاوية (لأن الكود يستخدم app())
  $this->app->instance(RabbitMQPublisher::class, $publisherMock);

  // 3. تنفيذ الـ Listener
  $event = new UserLoggedIn(123);
  $listener = new PublishLoginLog();
  $listener->handle($event);
});
