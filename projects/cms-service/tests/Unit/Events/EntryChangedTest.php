<?php

namespace Tests\Unit\Events;

use App\Events\EntryChanged;
use App\Models\DataEntry;
use Illuminate\Broadcasting\PrivateChannel;

test('it sets properties correctly in constructor', function () {
  $entry = new DataEntry();
  $userId = 99;

  $event = new EntryChanged($entry, $userId);

  expect($event->entry)->toBe($entry)
    ->and($event->userId)->toBe($userId);
});

test('it broadcasts on the correct private channel', function () {
  $event = new EntryChanged(new DataEntry());
  $channels = $event->broadcastOn();

  // التأكد من وجود قناة واحدة
  expect($channels)->toHaveCount(1);

  // التأكد من أنها من نوع PrivateChannel
  expect($channels[0])->toBeInstanceOf(PrivateChannel::class);

  // تذكر: لارافل يضيف البادئة 'private-' تلقائياً
  expect($channels[0]->name)->toBe('private-channel-name');
});
