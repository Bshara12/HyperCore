<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\ResolveStateAction;
use App\Domains\CMS\States\DataEntryStateResolver;
use App\Domains\CMS\States\DataEntryState; // تأكد من استيراد الكلاس الأساسي
use App\Models\DataEntry;
use Mockery;

afterEach(function () {
  Mockery::close();
});

test('it resolves state to published correctly', function () {
  $entry = new DataEntry();

  // الحل هنا: حددنا النوع (DataEntryState::class) داخل الموك
  $stateMock = Mockery::mock(DataEntryState::class);
  $resolverMock = Mockery::mock(DataEntryStateResolver::class);

  $resolverMock->shouldReceive('resolve')
    ->once()
    ->with($entry)
    ->andReturn($stateMock);

  // نتوقع استدعاء publish على الـ state
  $stateMock->shouldReceive('publish')
    ->once()
    ->with($entry);

  $action = new ResolveStateAction($resolverMock);
  $action->execute($entry, 'published', null);

  // لا نحتاج لـ expect(true) إذا كان الاختبار لا يرمي استثناءً
  expect(true)->toBeTrue();
});

test('it resolves state to scheduled correctly', function () {
  $entry = new DataEntry();
  $scheduledAt = '2026-06-01 10:00:00';

  // نفس الشيء هنا
  $stateMock = Mockery::mock(DataEntryState::class);
  $resolverMock = Mockery::mock(DataEntryStateResolver::class);

  $resolverMock->shouldReceive('resolve')
    ->once()
    ->with($entry)
    ->andReturn($stateMock);

  $stateMock->shouldReceive('schedule')
    ->once()
    ->with($entry, $scheduledAt);

  $action = new ResolveStateAction($resolverMock);
  $action->execute($entry, 'scheduled', $scheduledAt);

  expect(true)->toBeTrue();
});
