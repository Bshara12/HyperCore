<?php

namespace Tests\Unit\Events;

use App\Events\UserLoggedIn;
use Illuminate\Broadcasting\PrivateChannel;

it('correctly stores the user id in the constructor', function () {
  // 1. إعداد البيانات
  $userId = 123;

  // 2. إنشاء الـ Event
  $event = new UserLoggedIn($userId);

  // 3. التحقق من أن القيمة مخزنة في الخاصية العامة (Public Property)
  expect($event->userId)->toBe($userId);
});

it('broadcasts on the correct private channel', function () {
  // 1. إنشاء الـ Event
  $event = new UserLoggedIn(1);

  // 2. استدعاء تابع القنوات
  $channels = $event->broadcastOn();

  // 3. التحقق من نوع القناة واسمها
  expect($channels)->toBeArray()
    ->and($channels)->toHaveCount(1)
    ->and($channels[0])->toBeInstanceOf(PrivateChannel::class)
    ->and($channels[0]->name)->toBe('private-channel-name');
});
