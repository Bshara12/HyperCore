<?php

use App\Events\EntryRemovedFromSearch;
use Illuminate\Support\Facades\Event;

test('it initializes entry id and reason correctly', function () {
  $event = new EntryRemovedFromSearch(entryId: 123, reason: 'deleted');

  expect($event->entryId)->toBe(123)
    ->and($event->reason)->toBe('deleted');
});

test('it can be dispatched successfully', function () {
  Event::fake();

  EntryRemovedFromSearch::dispatch(456, 'unpublished');

  Event::assertDispatched(EntryRemovedFromSearch::class, function ($event) {
    return $event->entryId === 456 && $event->reason === 'unpublished';
  });
});
