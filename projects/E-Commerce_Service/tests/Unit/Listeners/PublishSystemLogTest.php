<?php

namespace Tests\Unit\Listeners;

use App\Events\SystemLogEvent;
use App\Listeners\PublishSystemLog;
use App\Domains\Core\Services\RabbitMQPublisher;
use Illuminate\Support\Facades\Date;
use Mockery;

afterEach(function () {
  Mockery::close();
});

it('publishes a normal system log without audit values', function () {
  // 1. تثبيت الوقت
  Date::setTestNow(now());
  $currentTime = now()->toDateTimeString();

  // 2. محاكاة الـ RabbitMQPublisher
  $publisherMock = Mockery::mock(RabbitMQPublisher::class);

  // التوقع: التأكد من عدم وجود قيم audit في المصفوفة
  $publisherMock->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) use ($currentTime) {
      return $payload['module'] === 'Payment' &&
        $payload['event_type'] === 'success' &&
        !isset($payload['old_values']) &&
        $payload['occurred_at'] === $currentTime;
    }));

  $this->app->instance(RabbitMQPublisher::class, $publisherMock);

  // 3. التنفيذ
  $event = new SystemLogEvent(module: 'Payment', eventType: 'success');
  (new PublishSystemLog())->handle($event);
});

it('publishes an audit log with old and new values when event type is audit', function () {
  Date::setTestNow(now());

  $publisherMock = Mockery::mock(RabbitMQPublisher::class);

  // التوقع: التأكد من وجود قيم audit
  $publisherMock->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) {
      return $payload['event_type'] === 'audit' &&
        $payload['old_values'] === ['status' => 'pending'] &&
        $payload['new_values'] === ['status' => 'active'];
    }));

  $this->app->instance(RabbitMQPublisher::class, $publisherMock);

  // 3. التنفيذ بحدث من نوع audit
  $event = new SystemLogEvent(
    module: 'User',
    eventType: 'audit',
    oldValues: ['status' => 'pending'],
    newValues: ['status' => 'active']
  );

  (new PublishSystemLog())->handle($event);
});
