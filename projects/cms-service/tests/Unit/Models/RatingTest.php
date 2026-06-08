<?php

use App\Models\Rating;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it belongs to a rateable model', function () {
  $user = User::factory()->create();
  $project = Project::factory()->create();

  $rating = Rating::create([
    'user_id' => $user->id,
    'rating' => 5,
    'review' => 'Excellent work!',
    'rateable_type' => Project::class,
    'rateable_id' => $project->id,
  ]);

  expect($rating->rateable)->toBeInstanceOf(Project::class)
    ->and($rating->rateable->id)->toBe($project->id);
});

test('it enforces unique rating per user per item', function () {
  $user = User::factory()->create();
  $project = Project::factory()->create();

  // إنشاء تقييم أول
  Rating::factory()->create([
    'user_id' => $user->id,
    'rateable_type' => Project::class,
    'rateable_id' => $project->id,
  ]);

  // محاولة إنشاء تقييم ثاني لنفس المستخدم على نفس المشروع (يجب أن يفشل)
  expect(fn() => Rating::factory()->create([
    'user_id' => $user->id,
    'rateable_type' => Project::class,
    'rateable_id' => $project->id,
  ]))->toThrow(\Illuminate\Database\QueryException::class);
});
