<?php

use App\Domains\CMS\Requests\CreateDataCollectionRequest;
use App\Models\DataType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

/*
 | The rules now hit the database: data_type_id must exist inside the current
 | project and slug must be unique inside it. Both need a resolved project and
 | real rows, so these are no longer schema-free.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
  $this->project = bindCurrentProject();
  $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);
});

function validCollectionPayload(array $overrides = []): array
{
  return array_merge([
    'name' => 'My Collection',
    'slug' => 'my-collection',
    'type' => 'manual',
    'data_type_id' => test()->dataType->id,
  ], $overrides);
}

test('it passes with valid data', function () {
  $data = validCollectionPayload([
    'conditions' => [
      ['field' => 'status', 'operator' => '=', 'value' => 'published'],
    ],
    'conditions_logic' => 'and',
    'description' => 'A test collection',
    'is_active' => true,
    'settings' => ['key' => 'value'],
  ]);

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  expect($validator->passes())->toBeTrue();
});

test('it fails when required fields are missing', function () {
  $validator = validateRequest([], CreateDataCollectionRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has(['name', 'slug', 'type', 'data_type_id']))->toBeTrue();
});

test('it validates enum constraints for type and logic', function ($key, $value, $shouldPass) {
  $data = validCollectionPayload([$key => $value]);

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  if ($shouldPass) {
    expect($validator->passes())->toBeTrue();
  } else {
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has($key))->toBeTrue();
  }
})->with([
  ['type', 'invalid_type', false],
  ['conditions_logic', 'invalid_logic', false],
  ['conditions_logic', 'or', true],
]);

test('it validates conditional rules for conditions array', function () {
  $data = validCollectionPayload([
    'type' => 'dynamic',
    'conditions' => [
      ['field' => 'status'], // missing operator and value
    ],
  ]);

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has([
    'conditions.0.operator',
    'conditions.0.value',
  ]))->toBeTrue();
});

test('it validates boolean type for is_active', function () {
  $data = validCollectionPayload(['is_active' => 'not-a-boolean']);

  $validator = validateRequest($data, CreateDataCollectionRequest::class);

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('is_active'))->toBeTrue();
});

test('it rejects a data type belonging to another project', function () {
  $otherProject = \App\Models\Project::factory()->create();
  $foreignType = DataType::factory()->create(['project_id' => $otherProject->id]);

  $validator = validateRequest(
    validCollectionPayload(['data_type_id' => $foreignType->id]),
    CreateDataCollectionRequest::class
  );

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('data_type_id'))->toBeTrue();
});

test('it rejects a slug already used inside the same project', function () {
  \App\Models\DataCollection::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'taken-slug',
  ]);

  $validator = validateRequest(
    validCollectionPayload(['slug' => 'taken-slug']),
    CreateDataCollectionRequest::class
  );

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('slug'))->toBeTrue();
});

test('it accepts a slug already used by a different project', function () {
  $otherProject = \App\Models\Project::factory()->create();

  \App\Models\DataCollection::factory()->create([
    'project_id' => $otherProject->id,
    'slug' => 'shared-slug',
  ]);

  $validator = validateRequest(
    validCollectionPayload(['slug' => 'shared-slug']),
    CreateDataCollectionRequest::class
  );

  expect($validator->passes())->toBeTrue();
});

test('it rejects a slug that could collide with a cache key', function () {
  // Cache keys embed the slug: project:{id}:collections:{slug}. alpha_dash keeps
  // separators like ":" out of it.
  $validator = validateRequest(
    validCollectionPayload(['slug' => '1:collections:evil']),
    CreateDataCollectionRequest::class
  );

  expect($validator->fails())->toBeTrue();
  expect($validator->errors()->has('slug'))->toBeTrue();
});
