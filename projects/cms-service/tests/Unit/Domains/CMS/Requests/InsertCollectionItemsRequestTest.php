<?php

use App\Domains\CMS\Requests\InsertCollectionItemsRequest;
use App\Models\DataCollection;
use App\Models\DataEntry;
use App\Models\DataType;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->project = bindCurrentProject();

  $this->dataType = DataType::factory()->create(['project_id' => $this->project->id]);

  $this->collection = DataCollection::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
    'slug' => 'my-collection',
  ]);

  // The rules read the collection off the route, so the request has to look
  // like it was dispatched on /collections/{collectionSlug}/insert.
  $this->request = requestWithRouteParams(
    InsertCollectionItemsRequest::class,
    ['collectionSlug' => 'my-collection']
  );

  $this->entryInCollection = fn () => DataEntry::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $this->dataType->id,
  ]);
});

test('it passes validation with valid item IDs', function () {
  $entry1 = ($this->entryInCollection)();
  $entry2 = ($this->entryInCollection)();

  $validator = Validator::make(
    ['items' => [$entry1->id, $entry2->id]],
    $this->request->rules()
  );

  expect($validator->passes())->toBeTrue();
});

test('it fails if items is not an array or is empty', function ($items) {
  $validator = Validator::make(['items' => $items], $this->request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'not an array' => ['not-an-array'],
  'empty array' => [[]],
]);

test('it fails if item IDs are not integers or do not exist', function ($items) {
  $validator = Validator::make(['items' => $items], $this->request->rules());

  expect($validator->fails())->toBeTrue();
})->with([
  'non-integer' => [['abc']],
  'non-existent ID' => [[999]],
]);

test('it fails if item IDs are not distinct', function () {
  $entry = ($this->entryInCollection)();

  $validator = Validator::make(
    ['items' => [$entry->id, $entry->id]],
    $this->request->rules()
  );

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('items.1'))->toBeTrue();
});

test('it rejects an entry from another project', function () {
  $otherProject = \App\Models\Project::factory()->create();

  $foreignEntry = DataEntry::factory()->create([
    'project_id' => $otherProject->id,
    'data_type_id' => $this->dataType->id,
  ]);

  $validator = Validator::make(['items' => [$foreignEntry->id]], $this->request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('items.0'))->toBeTrue();
});

test('it rejects an entry of a different data type', function () {
  // A collection is bound to one data type; mixing types here is what let an
  // article end up inside a product collection and get priced.
  $otherType = DataType::factory()->create(['project_id' => $this->project->id]);

  $wrongTypeEntry = DataEntry::factory()->create([
    'project_id' => $this->project->id,
    'data_type_id' => $otherType->id,
  ]);

  $validator = Validator::make(['items' => [$wrongTypeEntry->id]], $this->request->rules());

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('items.0'))->toBeTrue();
});

test('it refuses to resolve a collection from another project', function () {
  $otherProject = \App\Models\Project::factory()->create();

  DataCollection::factory()->create([
    'project_id' => $otherProject->id,
    'slug' => 'their-collection',
  ]);

  $request = requestWithRouteParams(
    InsertCollectionItemsRequest::class,
    ['collectionSlug' => 'their-collection']
  );

  expect(fn () => $request->rules())->toThrow(ModelNotFoundException::class);
});

test('it caps how many items may be inserted at once', function () {
  $validator = Validator::make(
    ['items' => range(1, 501)],
    $this->request->rules()
  );

  expect($validator->fails())->toBeTrue()
    ->and($validator->errors()->has('items'))->toBeTrue();
});

test('it returns custom validation messages', function () {
  $rules = $this->request->rules();
  $messages = $this->request->messages();

  $validator = Validator::make(['items' => 'string'], $rules, $messages);
  expect($validator->errors()->first('items'))->toBe('The items field must be an array.');

  $validator = Validator::make(['items' => ['invalid-id']], $rules, $messages);
  expect($validator->errors()->first('items.0'))->toBe('The item_id must be a valid integer.');

  $validator = Validator::make(['items' => [1, 1]], $rules, $messages);
  expect($validator->errors()->first('items.1'))->toBe('Duplicate item_id values are not allowed.');

  $validator = Validator::make(['items' => [99999]], $rules, $messages);
  expect($validator->errors()->first('items.0'))->toBe('One or more item_id values do not exist in the database.');
});
