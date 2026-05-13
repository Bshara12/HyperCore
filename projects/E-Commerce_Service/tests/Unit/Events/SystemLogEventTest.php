<?php

namespace Tests\Unit\Events;

use App\Events\SystemLogEvent;
use Illuminate\Broadcasting\PrivateChannel;

it('stores the data correctly in the constructor', function () {
  $data = [
    'module' => 'Payment',
    'eventType' => 'payment.success',
    'userId' => 1,
    'entityType' => 'Transaction',
    'entityId' => 100,
    'oldValues' => ['status' => 'pending'],
    'newValues' => ['status' => 'completed'],
  ];

  $event = new SystemLogEvent(
    $data['module'],
    $data['eventType'],
    $data['userId'],
    $data['entityType'],
    $data['entityId'],
    $data['oldValues'],
    $data['newValues']
  );

  // التحقق من أن جميع القيم تم تخزينها بشكل صحيح
  expect($event->module)->toBe($data['module'])
    ->and($event->eventType)->toBe($data['eventType'])
    ->and($event->userId)->toBe($data['userId'])
    ->and($event->entityType)->toBe($data['entityType'])
    ->and($event->entityId)->toBe($data['entityId'])
    ->and($event->oldValues)->toBe($data['oldValues'])
    ->and($event->newValues)->toBe($data['newValues']);
});

it('handles null values for optional parameters', function () {
  $event = new SystemLogEvent('User', 'login');

  expect($event->userId)->toBeNull()
    ->and($event->entityType)->toBeNull()
    ->and($event->oldValues)->toBeNull();
});

it('broadcasts on the correct private channel', function () {
  $event = new SystemLogEvent('System', 'test');

  $channels = $event->broadcastOn();

  expect($channels)->toBeArray()
    ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
    ->and($channels[0]->name)->toBe('private-channel-name');
  // ملاحظة: PrivateChannel يضيف كلمة 'private-' تلقائياً للاسم
});
