<?php

use App\Models\SearchIndex;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it filters search indices by scope', function () {
  $project = Project::factory()->create();

  // إنشاء بيانات مختلفة
  SearchIndex::factory()->create(['project_id' => $project->id, 'language' => 'en', 'status' => 'published']);
  SearchIndex::factory()->create(['project_id' => $project->id, 'language' => 'ar', 'status' => 'draft']);

  // اختبار الـ Scopes
  expect(SearchIndex::forProject($project->id)->count())->toBe(2)
    ->and(SearchIndex::forLanguage('en')->count())->toBe(1)
    ->and(SearchIndex::published()->count())->toBe(1);
});

test('it correctly casts meta data', function () {
  $index = SearchIndex::factory()->create([
    'meta' => ['key' => 'value']
  ]);

  expect($index->meta)->toBeArray()
    ->and($index->meta['key'])->toBe('value');
});
