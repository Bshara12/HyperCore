<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\GenerateDynamicItemsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Services\DynamicCollectionQueryBuilder;
use App\Models\DataCollection;
use Mockery;

beforeEach(function () {
  $this->mock(\App\Domains\Core\Services\CircuitBreakerService::class, function ($mock) {
    $mock->shouldReceive('canProceed')->andReturn(true);
    $mock->shouldIgnoreMissing();
  });
});

afterEach(function () {
  Mockery::close();
});

test('it builds dynamic entries and replaces the collection items in one write', function () {
  $collection = new DataCollection();
  $collection->id = 10;
  $collection->slug = 'dyn-collection';
  $collection->project_id = 1;

  $entries = [
    (object) ['id' => 100],
    (object) ['id' => 200],
  ];

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $builderMock = Mockery::mock(DynamicCollectionQueryBuilder::class);

  $builderMock->shouldReceive('build')
    ->once()
    ->with($collection)
    ->andReturn($entries);

  // One bulk replace, not one INSERT per matched entry.
  $repoMock->shouldReceive('replaceItems')
    ->once()
    ->with(10, [100, 200]);

  $action = new GenerateDynamicItemsAction($repoMock, $builderMock);
  $result = $action->execute($collection);

  expect($result->all())->toBe($entries);
});

test('it caps how many entries a dynamic collection may materialise', function () {
  $collection = new DataCollection();
  $collection->id = 11;
  $collection->slug = 'dyn-collection';
  $collection->project_id = 1;

  $matched = collect(range(1, GenerateDynamicItemsAction::MAX_ITEMS + 50))
    ->map(fn ($id) => (object) ['id' => $id])
    ->all();

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $builderMock = Mockery::mock(DynamicCollectionQueryBuilder::class);

  $builderMock->shouldReceive('build')->once()->andReturn($matched);

  $repoMock->shouldReceive('replaceItems')
    ->once()
    ->withArgs(function (int $collectionId, array $entryIds) {
      return $collectionId === 11
        && count($entryIds) === GenerateDynamicItemsAction::MAX_ITEMS
        && $entryIds[0] === 1;
    });

  $result = (new GenerateDynamicItemsAction($repoMock, $builderMock))->execute($collection);

  expect($result)->toHaveCount(GenerateDynamicItemsAction::MAX_ITEMS);
});

test('it replaces items with an empty set when nothing matches', function () {
  $collection = new DataCollection();
  $collection->id = 12;
  $collection->slug = 'dyn-collection';
  $collection->project_id = 1;

  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $builderMock = Mockery::mock(DynamicCollectionQueryBuilder::class);

  $builderMock->shouldReceive('build')->once()->andReturn(collect([]));

  // Still a replace: a regenerated collection that now matches nothing must end
  // up empty, not keep the items from the previous generation.
  $repoMock->shouldReceive('replaceItems')->once()->with(12, []);

  $result = (new GenerateDynamicItemsAction($repoMock, $builderMock))->execute($collection);

  expect($result)->toHaveCount(0);
});
