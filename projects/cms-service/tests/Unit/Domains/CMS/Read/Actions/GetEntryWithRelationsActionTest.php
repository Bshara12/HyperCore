<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetEntryWithRelationsAction;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryRelationRepository;
use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\CMS\Support\LanguageResolver;
use Exception;

beforeEach(function () {
  $this->entryRepo = mock(EntryReadRepository::class);
  $this->relationRepo = mock(EntryRelationRepository::class);
  $this->langResolver = mock(LanguageResolver::class);
  $this->visibilityService = mock(EntryVisibilityService::class);

  $this->action = new GetEntryWithRelationsAction(
    $this->entryRepo,
    $this->relationRepo,
    $this->langResolver,
    $this->visibilityService
  );

  // تهيئة الـ Request للتحقق من Auth User
  request()->attributes->set('auth_user', ['id' => 1]);
});

test('it returns null if main entry not found', function () {
  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  $this->entryRepo->shouldReceive('findPublishedWithValues')
    ->once()
    ->andReturn(null);

  $result = $this->action->execute(1, 'en');

  expect($result)->toBeNull();
});

test('it throws exception if entry is not visible (no subscription)', function () {
  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  $mainEntry = ['id' => 1, 'title' => 'Test'];

  $this->entryRepo->shouldReceive('findPublishedWithValues')->andReturn($mainEntry);

  // محاكاة أن الخدمة أعادت مصفوفة فارغة (أي غير مرئي للمستخدم)
  $this->visibilityService->shouldReceive('filterVisible')
    ->once()
    ->andReturn([]);

  expect(fn() => $this->action->execute(1, 'en'))
    ->toThrow(Exception::class, 'subsicribe to show it');
});

test('it successfully retrieves entry with parents and children', function () {
  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  $main = ['id' => 1];
  $parent = ['id' => 10];
  $child = ['id' => 20];

  // 1. Main Entry
  $this->entryRepo->shouldReceive('findPublishedWithValues')->andReturn($main);
  $this->visibilityService->shouldReceive('filterVisible')->with([$main], 1)->andReturn([$main]);

  // 2. Parents
  $this->relationRepo->shouldReceive('getParentIds')->with(1)->andReturn([10]);
  $this->entryRepo->shouldReceive('findPublishedManyWithValues')->with([10], 'en', 'en')->andReturn([$parent]);
  $this->visibilityService->shouldReceive('filterVisible')->with([$parent], 1)->andReturn([$parent]);

  // 3. Children
  $this->relationRepo->shouldReceive('getChildIds')->with(1)->andReturn([20]);
  $this->entryRepo->shouldReceive('findPublishedManyWithValues')->with([20], 'en', 'en')->andReturn([$child]);
  $this->visibilityService->shouldReceive('filterVisible')->with([$child], 1)->andReturn([$child]);

  $result = $this->action->execute(1, 'en');

  expect($result)->toBeArray()
    ->and($result['entry'])->toBe([$main])
    ->and($result['parents'])->toBe([$parent])
    ->and($result['children'])->toBe([$child]);
});
