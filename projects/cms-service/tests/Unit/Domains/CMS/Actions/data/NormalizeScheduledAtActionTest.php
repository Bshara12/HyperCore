<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\NormalizeScheduledAtAction;
use DomainException;

test('it returns null if status is not scheduled', function () {
  $action = new NormalizeScheduledAtAction();

  // حتى لو مررنا تاريخاً، يجب أن يعود null لأن الحالة ليست 'scheduled'
  $result = $action->execute('2026-05-27 10:00:00', 'published');

  expect($result)->toBeNull();
});

test('it throws exception if status is scheduled but date is missing', function () {
  $action = new NormalizeScheduledAtAction();

  // اختبار الحالات الفارغة
  expect(fn() => $action->execute(null, 'scheduled'))
    ->toThrow(DomainException::class, 'scheduled_at is required when status is scheduled.');

  expect(fn() => $action->execute('', 'scheduled'))
    ->toThrow(DomainException::class, 'scheduled_at is required when status is scheduled.');
});

test('it normalizes the date correctly when status is scheduled', function () {
  $action = new NormalizeScheduledAtAction();

  // تمرير تاريخ بصيغة مختلفة لنتأكد أن Carbon تقوم بـ Normalization
  $inputDate = '27-05-2026 14:30';
  $expectedDate = '2026-05-27 14:30:00';

  $result = $action->execute($inputDate, 'scheduled');

  expect($result)->toBe($expectedDate);
});
