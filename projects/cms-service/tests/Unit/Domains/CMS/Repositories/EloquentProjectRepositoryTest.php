<?php

namespace Tests\Unit\Domains\CMS\Repositories;

use App\Domains\CMS\Repositories\Eloquent\EloquentProjectRepository;
use App\Models\Project;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
  $this->repository = new EloquentProjectRepository();
});

test('create inserts a new project', function () {
  // 1. إنشاء مستخدم أولاً لتوفير owner_id
  $user = \App\Models\User::factory()->create();

  $data = [
    'name' => 'Test Project',
    'public_id' => 'proj-123',
    'owner_id' => $user->id // إضافة owner_id هنا
  ];

  $project = $this->repository->create($data);

  $this->assertDatabaseHas('projects', ['name' => 'Test Project', 'owner_id' => $user->id]);
  expect($project->id)->toBeGreaterThan(0);
});

test('update modifies project and returns refreshed instance', function () {
  $project = Project::factory()->create(['name' => 'Old Name']);

  $updated = $this->repository->update($project, ['name' => 'New Name']);

  expect($updated->name)->toBe('New Name');
  $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'New Name']);
});

test('find returns the same project instance', function () {
  $project = Project::factory()->create();
  $found = $this->repository->find($project);

  expect($found->id)->toBe($project->id);
});

test('findByKey returns project by public_id', function () {
  $project = Project::factory()->create(['public_id' => 'secret-123']);

  $found = $this->repository->findByKey('secret-123');

  expect($found->id)->toBe($project->id);
});

test('findByKey throws exception if not found', function () {
  expect(fn() => $this->repository->findByKey('non-existent'))
    ->toThrow(ModelNotFoundException::class);
});

test('all returns projects sorted by latest', function () {
  Project::factory()->create(['created_at' => now()->subDay()]);
  Project::factory()->create(['created_at' => now()]);

  $projects = $this->repository->all();

  expect($projects)->toHaveCount(2)
    ->and($projects->first()->created_at->greaterThan($projects->last()->created_at))->toBeTrue();
});

test('delete removes project from database', function () {
  $project = Project::factory()->create();

  $this->repository->delete($project);

  // 2. إذا كنت تستخدم SoftDeletes، استخدم هذا التأكيد بدلاً من assertDatabaseMissing
  $this->assertSoftDeleted('projects', ['id' => $project->id]);
});
test('findById returns project by id', function () {
  $project = Project::factory()->create();
  $found = $this->repository->findById($project->id);

  expect($found->id)->toBe($project->id);
});

test('findById throws exception if not found', function () {
  expect(fn() => $this->repository->findById(999))
    ->toThrow(ModelNotFoundException::class);
});

test('updateRatingStats updates project values in database', function () {
  $project = Project::factory()->create();
  $stats = ['ratings_count' => 10, 'ratings_avg' => 4.5];

  $this->repository->updateRatingStats($project->id, $stats);

  $this->assertDatabaseHas('projects', [
    'id' => $project->id,
    'ratings_count' => 10,
    'ratings_avg' => 4.5
  ]);
});

test('getRatingStats returns correct array when project exists', function () {
  $project = Project::factory()->create(['ratings_count' => 5, 'ratings_avg' => 3.0]);

  $stats = $this->repository->getRatingStats($project->id);

  expect($stats)->toBe(['ratings_count' => 5, 'ratings_avg' => 3.0]);
});

test('getRatingStats returns zero values when project does not exist', function () {
  $stats = $this->repository->getRatingStats(999);

  expect($stats)->toBe(['ratings_count' => 0, 'ratings_avg' => 0]);
});
