<?php

use App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO;
use App\Domains\CMS\Requests\UpdateDataCollectionRequest;
use App\Models\DataCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // هذا سيجعل قاعدة البيانات نظيفة لكل اختبار

test('it updates DTO and generates slug when name is provided', function () {
  // Factory سيقوم بإنشاء الـ User، ثم الـ Project، ثم الـ DataCollection في سطر واحد!
  $collection = DataCollection::factory()->create([
    'slug' => 'old-slug',
    'name' => 'Old Name',
  ]);

  $request = new UpdateDataCollectionRequest();
  $request->merge([
    'name' => 'New Collection Name',
  ]);

  $dto = UpdateDataCollectionDTO::fromRequest($request, 'old-slug');

  expect($dto->collection_id)->toBe($collection->id)
    ->and($dto->data['slug'])->toBe('new-collection-name');
});

test('it does not generate slug when name is missing', function () {
  // Factory سيتكفل بإنشاء كل الـ Dependencies (المشروع، المستخدم، النوع)
  $collection = \App\Models\DataCollection::factory()->create([
    'slug' => 'slug-1',
    'name' => 'Name 1'
  ]);

  $request = new \App\Domains\CMS\Requests\UpdateDataCollectionRequest();
  $request->merge([
    'description' => 'Only description',
  ]);

  $dto = \App\Domains\CMS\DTOs\DataCollection\UpdateDataCollectionDTO::fromRequest($request, 'slug-1');

  expect($dto->data)->not->toHaveKey('slug');
});

test('it returns data correctly via toArray', function () {
  $dto = new UpdateDataCollectionDTO(1, ['name' => 'test']);

  expect($dto->toArray())->toBe(['name' => 'test']);
});
