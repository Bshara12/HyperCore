<?php

namespace Tests\Feature\Listeners;

use App\Events\SystemLogEvent;
use App\Listeners\PublishSystemLog;
use App\Services\RabbitMQPublisher;
use Illuminate\Support\Str;
use Mockery;

beforeEach(function () {
  // تثبيت الوقت الحالي لضمان تطابق حقل occurred_at في جميع الفحوصات
  $this->travelTo(now());
});

// =========================================================================
// 1. اختبار المسار الأول: حدث عادي (ليس Audit) وضمان عدم إرسال قيم التعديل
// =========================================================================
test('it publishes standard system log to rabbitmq without audit values', function () {
  $expectedTimestamp = now()->toDateTimeString();

  // إنشاء الحدث وتمرير المعاملات المطلوبة للـ Constructor وإسناد الباقي كـ Properties
  $event = new SystemLogEvent(module: 'billing', eventType: 'invoice.created');
  $event->userId = 10;
  $event->entityType = 'Invoice';
  $event->entityId = 550;

  // بناء الـ Mock للخدمة الخارجية
  $mockPublisher = mock(RabbitMQPublisher::class);
  $mockPublisher->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) use ($expectedTimestamp) {
      return Str::isUuid($payload['event_id']) &&
        $payload['module'] === 'billing' &&
        $payload['event_type'] === 'invoice.created' &&
        $payload['user_id'] === 10 &&
        $payload['entity_type'] === 'Invoice' &&
        $payload['entity_id'] === 550 &&
        $payload['occurred_at'] === $expectedTimestamp &&
        !array_key_exists('old_values', $payload) &&
        !array_key_exists('new_values', $payload);
    }));

  $this->app->instance(RabbitMQPublisher::class, $mockPublisher);

  // تشغيل الـ Listener يدوياً
  $listener = new PublishSystemLog();
  $listener->handle($event);
});

// =========================================================================
// 2. اختبار المسار الثاني: حدث من نوع audit وضمان دمج قيم التعديل بنجاح
// =========================================================================
test('it publishes audit system log to rabbitmq including old and new values', function () {
  $expectedTimestamp = now()->toDateTimeString();

  // إنشاء حدث الـ Audit وتمرير البيانات الأساسية ثم بيانات التعديل
  $event = new SystemLogEvent(module: 'users', eventType: 'audit');
  $event->userId = 1;
  $event->entityType = 'User';
  $event->entityId = 99;
  $event->oldValues = ['status' => 'pending'];
  $event->newValues = ['status' => 'active'];

  // بناء الـ Mock للخدمة الخارجية
  $mockPublisher = mock(RabbitMQPublisher::class);
  $mockPublisher->shouldReceive('publish')
    ->once()
    ->with(Mockery::on(function ($payload) use ($expectedTimestamp) {
      return Str::isUuid($payload['event_id']) &&
        $payload['module'] === 'users' &&
        $payload['event_type'] === 'audit' &&
        $payload['user_id'] === 1 &&
        $payload['entity_type'] === 'User' &&
        $payload['entity_id'] === 99 &&
        $payload['occurred_at'] === $expectedTimestamp &&
        $payload['old_values'] === ['status' => 'pending'] &&
        $payload['new_values'] === ['status' => 'active'];
    }));

  $this->app->instance(RabbitMQPublisher::class, $mockPublisher);

  // تشغيل الـ Listener يدوياً
  $listener = new PublishSystemLog();
  $listener->handle($event);
});
