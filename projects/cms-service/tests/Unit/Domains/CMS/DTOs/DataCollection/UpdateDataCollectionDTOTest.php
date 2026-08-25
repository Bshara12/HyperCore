<?php

use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it keeps the slug unchanged when the name is updated', function () {
  $project = bindCurrentProject();

  $collection = DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'old-slug',
    'name' => 'Old Name',
  ]);

  $request = new UpdateDataCollectionRequest();
  $request->merge(['name' => 'New Collection Name']);

  $dto = UpdateDataCollectionDTO::fromRequest($request, 'old-slug');

  // The slug is the resource address: E-Commerce stores offers by it, the cache
  // is keyed by it and it is the route itself. Renaming must not re-address it.
  expect($dto->collection_id)->toBe($collection->id)
    ->and($dto->data)->not->toHaveKey('slug')
    ->and($dto->slug)->toBe('old-slug')
    ->and($dto->project_id)->toBe($project->id);
});

test('it does not add a slug when name is missing', function () {
  $project = bindCurrentProject();

  DataCollection::factory()->create([
    'project_id' => $project->id,
    'slug' => 'slug-1',
    'name' => 'Name 1',
  ]);

  $request = new UpdateDataCollectionRequest();
  $request->merge(['description' => 'Only description']);

  $dto = UpdateDataCollectionDTO::fromRequest($request, 'slug-1');

  expect($dto->data)->not->toHaveKey('slug')
    ->and($dto->data['description'])->toBe('Only description');
});

test('it refuses to resolve a collection belonging to another project', function () {
  $mine = bindCurrentProject();
  $theirs = \App\Models\Project::factory()->create();

  // Same slug in two projects is legal: the unique index is (project_id, slug).
  DataCollection::factory()->create(['project_id' => $theirs->id, 'slug' => 'shared-slug']);

  $request = new UpdateDataCollectionRequest();
  $request->merge(['name' => 'Hijacked']);

  expect(fn () => UpdateDataCollectionDTO::fromRequest($request, 'shared-slug'))
    ->toThrow(ModelNotFoundException::class);

  expect($mine->id)->not->toBe($theirs->id);
});

test('it returns data correctly via toArray', function () {
  $dto = new UpdateDataCollectionDTO(1, 2, 'a-slug', ['name' => 'test']);

  expect($dto->toArray())->toBe(['name' => 'test']);
});
