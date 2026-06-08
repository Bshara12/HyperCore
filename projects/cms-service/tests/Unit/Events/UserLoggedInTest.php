<?php

namespace Tests\Unit\Events;

use App\Events\UserLoggedIn;
use Illuminate\Broadcasting\PrivateChannel;

test('it stores the userId correctly', function () {
  $userId = 123;
  $event = new UserLoggedIn($userId);

  expect($event->userId)->toBe($userId);
});

test('it broadcasts on the correct private channel', function () {
  $event = new UserLoggedIn(123);
  $channels = $event->broadcastOn();

  // التأكد من وجود القناة الصحيحة
  expect($channels)->toHaveCount(1)
    ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
    // تذكر: لارافل يضيف البادئة 'private-' تلقائياً
    ->and($channels[0]->name)->toBe('private-channel-name');
});
