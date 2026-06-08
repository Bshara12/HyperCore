<?php

use App\Domains\CMS\States\PublishedState;
use App\Models\DataEntry;

beforeEach(function () {
  $this->state = new PublishedState();
  $this->entry = new DataEntry();
});

test('it throws exception when publishing an already published entry', function () {
  // نستخدم دالة مجهولة (Closure) داخل expect لكي يستطيع Pest التقاط الـ Exception
  expect(fn() => $this->state->publish($this->entry))
    ->toThrow(Exception::class, 'Already published.');
});

test('it throws exception when scheduling a published entry', function () {
  expect(fn() => $this->state->schedule($this->entry, '2026-06-01 10:00:00'))
    ->toThrow(Exception::class, 'Cannot schedule a published entry.');
});
