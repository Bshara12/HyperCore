<?php

use App\Domains\CMS\DTOs\DataType\CreateDataTypeDTO;
use App\Domains\CMS\DTOs\DataType\UpdateDataTypeDTO;
use App\Domains\CMS\Repositories\Eloquent\DataTypeRepositoryEloquent;
use App\Models\DataType;
use App\Models\Project;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);
beforeEach(function () {
  $this->repository = new DataTypeRepositoryEloquent();
  $this->project = Project::factory()->create();
});

test('it can create a data type', function () {
  $dto = new CreateDataTypeDTO(
    project_id: $this->project->id,
    name: 'Test Type',
    slug: 'test-type'
  );

  $dataType = $this->repository->create($dto);

  expect($dataType->name)->toBe('Test Type')
    ->and($dataType->project_id)->toBe($this->project->id);
  $this->assertDatabaseHas('data_types', ['slug' => 'test-type']);
});

test('it throws 422 when ensuring unique slug fails (create)', function () {
  DataType::factory()->create(['project_id' => $this->project->id, 'slug' => 'taken']);

  // تغيير النوع إلى HttpException
  expect(fn() => $this->repository->ensureSlugIsUnique($this->project->id, 'taken'))
    ->toThrow(HttpException::class);
});

test('it finds data type by slug', function () {
  $created = DataType::factory()->create(['project_id' => $this->project->id, 'slug' => 'find-me']);

  $found = $this->repository->findBySlug('find-me', $this->project->id);

  expect($found->id)->toBe($created->id);
});

test('it ensures slug is unique for update ignoring current id', function () {
  $existing = DataType::factory()->create(['project_id' => $this->project->id, 'slug' => 'taken']);

  DataType::factory()->create(['project_id' => $this->project->id, 'slug' => 'taken-2']);

  // تغيير النوع إلى HttpException
  expect(fn() => $this->repository->ensureSlugIsUniqueForUpdate($this->project->id, 'taken-2', $existing->id))
    ->toThrow(HttpException::class);
});

test('it can update a data type', function () {
  $dataType = DataType::factory()->create(['project_id' => $this->project->id]);
  $dto = new UpdateDataTypeDTO(
    name: 'Updated Name',
    slug: 'updated-slug'
  );

  $updated = $this->repository->update($dataType, $dto);

  expect($updated->name)->toBe('Updated Name')
    ->and($updated->slug)->toBe('updated-slug');
});

test('it can delete and restore data type', function () {
  $dataType = DataType::factory()->create();

  // حذف
  $this->repository->delete($dataType);
  $this->assertSoftDeleted('data_types', ['id' => $dataType->id]);

  // استعادة
  $this->repository->restore($dataType->id);
  $this->assertDatabaseHas('data_types', ['id' => $dataType->id, 'deleted_at' => null]);
});

test('it can force delete data type', function () {
  $dataType = DataType::factory()->create();

  $this->repository->forceDelete($dataType->id);

  $this->assertDatabaseMissing('data_types', ['id' => $dataType->id]);
});

test('it can get id by slug and project', function () {
  $dataType = DataType::factory()->create(['project_id' => $this->project->id, 'slug' => 'unique-slug']);

  $id = $this->repository->getIdBySlugAndProject('unique-slug', $this->project->id);

  expect($id)->toBe($dataType->id);
});
