<?php

namespace Tests\Unit\Events;

use App\Events\DataEntrySavedEvent;
use App\Models\DataEntry;

test('it correctly holds the data entry instance', function () {
  $entry = new DataEntry();
  $event = new DataEntrySavedEvent($entry);

  expect($event->entry)->toBe($entry);
});
