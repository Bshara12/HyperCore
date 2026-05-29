<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetEntriesByDataTypeSlugAction;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryTypeReadRepository;
use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

test('it returns error message if data type not found', function () {
  // 1. إعداد الموكات (Mocks)
  $dataTypeRepo = mock(DataTypeRepositoryInterface::class);
  $typeRepo = mock(EntryTypeReadRepository::class);
  $entryRepo = mock(EntryReadRepository::class);
  $languageResolver = mock(LanguageResolver::class);
  $visibilityService = mock(EntryVisibilityService::class);

  // 2. محاكاة فشل العثور على الـ Data Type
  $dataTypeRepo->shouldReceive('getIdBySlugAndProject')
    ->once()
    ->with('slug', 1)
    ->andReturn(null);

  // حل لغة مبدئي للـ resolver
  $languageResolver->shouldReceive('resolve')->andReturn('en');
  $languageResolver->shouldReceive('fallback')->andReturn('en');

  $action = new GetEntriesByDataTypeSlugAction(
    $dataTypeRepo,
    $typeRepo,
    $entryRepo,
    $languageResolver,
    $visibilityService
  );

  $result = $action->execute(1, 'slug', []);

  expect($result)->toBe(['message' => 'Data type not found']);
});

test('it successfully retrieves and paginates entries', function () {
  // 1. إعداد الموكات
  $dataTypeRepo = mock(DataTypeRepositoryInterface::class);
  $typeRepo = mock(EntryTypeReadRepository::class);
  $entryRepo = mock(EntryReadRepository::class);
  $languageResolver = mock(LanguageResolver::class);
  $visibilityService = mock(EntryVisibilityService::class);

  // الموك الخاص بـ Builder (Query)
  $query = mock(Builder::class);

  // 2. إعداد البيانات
  $projectId = 1;
  $slug = 'test-slug';
  $filters = ['page' => 1, 'per_page' => 10, 'lang' => 'en'];
  $entriesData = [['id' => 1], ['id' => 2]];
  // محاكاة الـ Paginator
  $paginator = new LengthAwarePaginator($entriesData, 2, 10, 1);

  // 3. تحديد التوقعات (Expectations)
  $languageResolver->shouldReceive('resolve')->andReturn('en');
  $languageResolver->shouldReceive('fallback')->andReturn('en');
  $dataTypeRepo->shouldReceive('getIdBySlugAndProject')->andReturn(10);

  // سلسلة الاستدعاء (Chain)
  $typeRepo->shouldReceive('filterPublishedByType')->andReturn($query);
  $query->shouldReceive('where')->with('project_id', $projectId)->once()->andReturn($query);
  $query->shouldReceive('paginate')->andReturn($paginator);

  $entryRepo->shouldReceive('findPublishedManyWithValues')->once()->andReturn($entriesData);

  // محاكاة الـ Request للـ Auth
  request()->attributes->set('auth_user', ['id' => 99]);
  $visibilityService->shouldReceive('filterVisible')->once()->andReturn($entriesData);

  // 4. التنفيذ
  $action = new GetEntriesByDataTypeSlugAction(
    $dataTypeRepo,
    $typeRepo,
    $entryRepo,
    $languageResolver,
    $visibilityService
  );

  $result = $action->execute($projectId, $slug, $filters);

  // 5. التأكيد
  expect($result)->toHaveKey('entries')
    ->and($result['entries'])->toBe($entriesData)
    ->and($result['meta']['current_page'])->toBe(1);
});
