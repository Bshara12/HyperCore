<?php

use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class); // هذا السطر هو الأهم لحل مشكلة no such table

test('it generates slug automatically upon creation', function () {
  $project = Project::factory()->create(['name' => 'Test Project Name']);

  expect($project->slug)->toBe('test-project-name')
    ->and($project->public_id)->not->toBeNull();
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
