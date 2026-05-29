<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetEntriesBySameTypeAction;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Read\Repositories\EntryTypeReadRepository;
use App\Domains\CMS\Read\Services\EntryVisibilityService;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

beforeEach(function () {
  $this->typeRepo = mock(EntryTypeReadRepository::class);
  $this->entryRepo = mock(EntryReadRepository::class);
  $this->langResolver = mock(LanguageResolver::class);
  $this->visibilityService = mock(EntryVisibilityService::class);

  $this->action = new GetEntriesBySameTypeAction(
    $this->typeRepo,
    $this->entryRepo,
    $this->langResolver,
    $this->visibilityService
  );
});

test('it returns null if data type not found', function () {
  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');
  $this->typeRepo->shouldReceive('getDataTypeId')->once()->andReturn(null);

  $result = $this->action->execute(1, 'en');

  expect($result)->toBeNull();
});

test('it retrieves entries with pagination successfully', function () {
  // 1. التجهيز
  $dataTypeId = 10;
  $entriesData = [['id' => 1], ['id' => 2]]; // مصفوفة لضمان التوافق مع النوع

  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');
  $this->typeRepo->shouldReceive('getDataTypeId')->andReturn($dataTypeId);

  // محاكاة الـ Query Builder والـ Pagination
  $query = mock(Builder::class);
  $paginator = new LengthAwarePaginator(collect($entriesData), 2, 20, 1);

  $this->typeRepo->shouldReceive('filterPublishedByType')->andReturn($query);
  $query->shouldReceive('paginate')->once()->andReturn($paginator);

  // 2. محاكاة جلب القيم والفلترة
  $this->entryRepo->shouldReceive('findPublishedManyWithValues')->andReturn($entriesData);
  $this->visibilityService->shouldReceive('filterVisible')->andReturn($entriesData);

  // 3. التنفيذ
  $result = $this->action->execute(1, 'en', page: 1, perPage: 20, all: false);

  // 4. التأكيد
  expect($result)->toBeArray()
    ->and($result['data_type_id'])->toBe($dataTypeId)
    ->and($result['entries'])->toBe($entriesData)
    ->and($result['meta']['current_page'])->toBe(1);
});

test('it retrieves all entries without pagination when all is true', function () {
  // 1. التجهيز
  $dataTypeId = 10;
  $entriesData = [['id' => 1], ['id' => 2]];
  $entriesCollection = collect($entriesData); // نحتاج كوليكشن حقيقي ليعمل pluck()

  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');
  $this->typeRepo->shouldReceive('getDataTypeId')->andReturn($dataTypeId);

  // محاكاة الـ Query Builder
  $query = mock(Builder::class);

  // التغيير الجوهري هنا:
  // نتوقع استدعاء get() وليس paginate()
  $this->typeRepo->shouldReceive('filterPublishedByType')->andReturn($query);
  $query->shouldReceive('get')->once()->andReturn($entriesCollection);
  $query->shouldNotReceive('paginate'); // نؤكد أنها لن تُستدعى

  // 2. محاكاة جلب القيم والفلترة
  $this->entryRepo->shouldReceive('findPublishedManyWithValues')->andReturn($entriesData);
  $this->visibilityService->shouldReceive('filterVisible')->andReturn($entriesData);

  // 3. التنفيذ مع all: true
  $result = $this->action->execute(1, 'en', all: true);

  // 4. التأكيد
  expect($result)->toBeArray()
    ->and($result['data_type_id'])->toBe($dataTypeId)
    ->and($result['entries'])->toBe($entriesData)
    ->and($result['meta'])->toBeNull(); // يجب أن تكون null لأننا مررنا all: true
});
