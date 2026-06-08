<?php

use App\Domains\CMS\States\DataEntryStateResolver;
use App\Domains\CMS\States\DraftState;
use App\Domains\CMS\States\PublishedState;
use App\Models\DataEntry;

beforeEach(function () {
  $this->resolver = new DataEntryStateResolver();
});

test('it returns DraftState when status is draft', function () {
  // استخدم الكائن الحقيقي بدلاً من الـ Mock
  $entry = new DataEntry();
  $entry->status = 'draft';

  $state = $this->resolver->resolve($entry);

  expect($state)->toBeInstanceOf(DraftState::class);
});

test('it returns PublishedState when status is published', function () {
  $entry = new DataEntry();
  $entry->status = 'published';

  $state = $this->resolver->resolve($entry);

  expect($state)->toBeInstanceOf(PublishedState::class);
});

test('it returns DraftState as default when status is unknown', function () {
  $entry = new DataEntry();
  $entry->status = 'archived';

  $state = $this->resolver->resolve($entry);

  expect($state)->toBeInstanceOf(DraftState::class);
});
