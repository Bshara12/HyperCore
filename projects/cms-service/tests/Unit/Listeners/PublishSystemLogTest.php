<?php

namespace Tests\Unit\Listeners;

use App\Listeners\PublishSystemLog;
use App\Domains\CMS\Services\RabbitMQPublisher;
use App\Events\SystemLogEvent;
use Illuminate\Support\Str;

/**
 * ─── System Log Fake RabbitMQ Publisher ───
 */
class SystemLogFakeRabbitMQPublisher extends RabbitMQPublisher
{
  public bool $wasPublished = false;
  public array $publishedData = [];

  public function __construct() {}

  public function publish(array $data): void
  {
    $this->wasPublished = true;
    $this->publishedData = $data;
  }
}

// ─── كود الاختبار والتغطية الشاملة ───────────────────────────────────────

test('it publishes standard system log payload without audit values', function () {
  $fakePublisher = new SystemLogFakeRabbitMQPublisher();
  app()->instance(RabbitMQPublisher::class, $fakePublisher);

  // 🔥 تمرير المعاملات الإجبارية للـ Constructor مباشرة لحل خطأ ArgumentCountError
  $event = new SystemLogEvent('cms', 'create');

  // تعيين باقي الخصائص الاختيارية التي يتوقعها الـ Listener
  $event->userId = 10;
  $event->entityType = 'Post';
  $event->entityId = 100;

  $listener = new PublishSystemLog();
  $listener->handle($event);

  expect($fakePublisher->wasPublished)->toBeTrue();

  $payload = $fakePublisher->publishedData;
  expect($payload['module'])->toBe('cms');
  expect($payload['event_type'])->toBe('create');
  expect($payload['user_id'])->toBe(10);
  expect($payload['entity_type'])->toBe('Post');
  expect($payload['entity_id'])->toBe(100);

  expect($payload)->not->toHaveKeys(['old_values', 'new_values']);
  expect(Str::isUuid($payload['event_id']))->toBeTrue();
  expect($payload['occurred_at'])->toBe(now()->toDateTimeString());
});


test('it appends old and new values when event type is audit', function () {
  $fakePublisher = new SystemLogFakeRabbitMQPublisher();
  app()->instance(RabbitMQPublisher::class, $fakePublisher);

  // 🔥 تمرير المعاملات الإجبارية هنا أيضاً تبعاً لنوع الـ الحدث
  $event = new SystemLogEvent('settings', 'audit');

  $event->userId = 10;
  $event->entityType = 'Configuration';
  $event->entityId = 1;
  $event->oldValues = ['maintenance_mode' => false];
  $event->newValues = ['maintenance_mode' => true];

  $listener = new PublishSystemLog();
  $listener->handle($event);

  expect($fakePublisher->wasPublished)->toBeTrue();

  $payload = $fakePublisher->publishedData;
  expect($payload['event_type'])->toBe('audit');

  expect($payload)->toHaveKeys(['old_values', 'new_values']);
  expect($payload['old_values'])->toBe(['maintenance_mode' => false]);
  expect($payload['new_values'])->toBe(['maintenance_mode' => true]);
});
