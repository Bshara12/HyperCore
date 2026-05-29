<?php

use App\Domains\CMS\States\DraftState;
use App\Models\DataEntry;

beforeEach(function () {
  $this->state = new DraftState();
});

test('publish changes status to published and sets published_at', function () {
  // 1. Arrange: إنشاء Mock للموديل
  $entry = Mockery::mock(DataEntry::class);

  // 2. Expectation: التأكد من أن update تُستدعى ببيانات النشر
  $entry->shouldReceive('update')
    ->once()
    ->with(Mockery::on(function ($argument) {
      return $argument['status'] === 'published' &&
        $argument['published_at'] !== null;
    }));

  // 3. Act
  $this->state->publish($entry);
});

test('schedule changes status to scheduled and sets scheduled_at', function () {
  // 1. Arrange
  $entry = Mockery::mock(DataEntry::class);
  $date = '2026-06-01 10:00:00';

  // 2. Expectation
  $entry->shouldReceive('update')
    ->once()
    ->with([
      'status' => 'scheduled',
      'scheduled_at' => $date,
    ]);

  // 3. Act
  $this->state->schedule($entry, $date);
});
