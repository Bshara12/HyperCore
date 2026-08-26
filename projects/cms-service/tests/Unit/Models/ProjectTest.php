<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // هذا السطر هو الأهم لحل مشكلة no such table

test('it generates slug automatically upon creation', function () {
  // slug must be absent for the derive-from-name path to be the one under
  // test — the factory supplies its own unique slug otherwise.
  $project = Project::factory()->create([
    'name' => 'Test Project Name',
    'slug' => null,
  ]);

  expect($project->slug)->toBe('test-project-name')
    ->and($project->public_id)->not->toBeNull();
});

test('it keeps a slug that was supplied explicitly', function () {
  /*
   | The hook used to overwrite unconditionally, which threw away any slug the
   | caller had set. Because the factory's slug was discarded, every project in
   | a test run got Str::slug($faker->company()) — and company() is not unique,
   | so a repeated name hit the global unique index on projects.slug and failed
   | the run at random.
   */
  $project = Project::factory()->create([
    'name' => 'Test Project Name',
    'slug' => 'a-deliberately-different-slug',
  ]);

  expect($project->slug)->toBe('a-deliberately-different-slug');
});

test('it uses slug as route key name', function () {
  $project = new Project();
  expect($project->getRouteKeyName())->toBe('slug');
});

test('it has relationships', function () {
  $project = Project::factory()->create();

  expect($project->payments())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
    ->and($project->collections())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class)
    ->and($project->ratings())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\MorphMany::class);
});
