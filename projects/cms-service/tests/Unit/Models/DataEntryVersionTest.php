<?php

use App\Models\DataEntryVersion;
use App\Models\DataEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it belongs to data entry', function () {
  $entry = DataEntry::factory()->create();
  $version = DataEntryVersion::factory()->create(['data_entry_id' => $entry->id]);

  expect($version->entry)->toBeInstanceOf(DataEntry::class)
    ->and($version->entry->id)->toBe($entry->id);
});

test('it casts snapshot to array', function () {
  $snapshot = ['key' => 'value', 'status' => 'draft'];
  $version = DataEntryVersion::factory()->create(['snapshot' => $snapshot]);

  expect($version->snapshot)->toBeArray()
    ->and($version->snapshot['key'])->toBe('value')
    ->and($version->snapshot)->toBe($snapshot);
});
