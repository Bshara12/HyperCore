<?php

namespace Tests\Unit\Domains\CMS\Read\Services;

use App\Domains\CMS\Read\Actions\GetEntryVersionsAction;
use App\Domains\CMS\Read\DTOs\EntryVersionsListDTO;
use App\Domains\CMS\Read\Services\EntryVersionReadService;
use Mockery;

beforeEach(function () {
  // إنشاء Mock للأكشن
  $this->getEntryVersionsAction = Mockery::mock(GetEntryVersionsAction::class);

  // حقن الـ Mock في الخدمة
  $this->service = new EntryVersionReadService($this->getEntryVersionsAction);
});

afterEach(function () {
  Mockery::close();
});

test('it calls the action with correct arguments and returns the expected DTO', function () {
  // 1. تعريف القيم
  $projectId = 10;
  $slug = 'test-entry-slug';
  $page = 2;
  $perPage = 50;
  $withSnapshot = true;

  // 2. إنشاء الـ DTO المتوقع
  $expectedDto = new EntryVersionsListDTO(
    total: 100,
    page: 2,
    per_page: 50,
    items: []
  );

  // 3. التعديل هنا: استخدم الوسائط بالترتيب (Positional) بدلاً من المسميات
  $this->getEntryVersionsAction
    ->shouldReceive('execute')
    ->once()
    ->with($projectId, $slug, $page, $perPage, $withSnapshot)
    ->andReturn($expectedDto);

  // 4. تنفيذ الخدمة
  $result = $this->service->listForEntrySlug(
    $projectId,
    $slug,
    $page,
    $perPage,
    $withSnapshot
  );

  expect($result)->toBe($expectedDto);
});
