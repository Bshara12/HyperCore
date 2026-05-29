<?php

namespace Tests\Unit\Events;

use App\Events\SystemLogEvent;
use Illuminate\Broadcasting\PrivateChannel;

test('it stores all properties correctly', function () {
  $event = new SystemLogEvent(
    module: 'payment',
    eventType: 'refund',
    userId: 1,
    entityType: 'Order',
    entityId: 100,
    oldValues: ['status' => 'paid'],
    newValues: ['status' => 'refunded']
  );

  expect($event->module)->toBe('payment')
    ->and($event->eventType)->toBe('refund')
    ->and($event->userId)->toBe(1)
    ->and($event->entityType)->toBe('Order')
    ->and($event->entityId)->toBe(100)
    ->and($event->oldValues)->toBe(['status' => 'paid'])
    ->and($event->newValues)->toBe(['status' => 'refunded']);
});

test('it broadcasts on the correct private channel', function () {
  $event = new SystemLogEvent('auth', 'login');
  $channels = $event->broadcastOn();

  // التأكد من القناة
  expect($channels)->toHaveCount(1)
    ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
    ->and($channels[0]->name)->toBe('private-channel-name');
});
