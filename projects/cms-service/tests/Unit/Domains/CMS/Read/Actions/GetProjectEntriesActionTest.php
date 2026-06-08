<?php

namespace Tests\Unit\Domains\CMS\Read\Actions;

use App\Domains\CMS\Read\Actions\GetProjectEntriesAction;
use App\Domains\CMS\Read\Repositories\EntryProjectReadRepositoryInterface;
use App\Domains\CMS\Read\Repositories\EntryReadRepository;
use App\Domains\CMS\Support\LanguageResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

beforeEach(function () {
  $this->projRepo = mock(EntryProjectReadRepositoryInterface::class);
  $this->entryRepo = mock(EntryReadRepository::class);
  $this->langResolver = mock(LanguageResolver::class);

  $this->action = new GetProjectEntriesAction(
    $this->projRepo,
    $this->entryRepo,
    $this->langResolver
  );
});

test('it retrieves paginated entries for a project with correct meta', function () {
  // 1. التجهيز
  $projectId = 1;
  $filters = ['page' => 1, 'per_page' => 5, 'lang' => 'en'];
  $entriesData = [['id' => 10], ['id' => 11]];

  $this->langResolver->shouldReceive('resolve')->andReturn('en');
  $this->langResolver->shouldReceive('fallback')->andReturn('en');

  // محاكاة الـ Query Builder والـ Pagination
  $query = mock(Builder::class);
  $paginator = new LengthAwarePaginator(
    collect($entriesData),
    total: 2,
    perPage: 5,
    currentPage: 1
  );

  $this->projRepo->shouldReceive('queryByProject')
    ->with($projectId, $filters)
    ->once()
    ->andReturn($query);

  $query->shouldReceive('paginate')
    ->with(5, ['*'], 'page', 1)
    ->once()
    ->andReturn($paginator);

  // محاكاة جلب التفاصيل
  $this->entryRepo->shouldReceive('findPublishedManyWithValues')
    ->with([10, 11], 'en', 'en')
    ->once()
    ->andReturn($entriesData);

  // 2. التنفيذ
  $result = $this->action->execute($projectId, $filters);

  // 3. التأكيد
  expect($result)->toBeArray()
    ->and($result['entries'])->toBe($entriesData)
    ->and($result['meta']['current_page'])->toBe(1)
    ->and($result['meta']['total'])->toBe(2)
    ->and($result['meta']['per_page'])->toBe(5);
});
