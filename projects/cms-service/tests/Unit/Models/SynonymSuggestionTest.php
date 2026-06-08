<?php

use App\Models\SynonymSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it correctly filters by status scopes', function () {
  SynonymSuggestion::factory()->create(['status' => 'pending']);
  SynonymSuggestion::factory()->approved()->create();

  expect(SynonymSuggestion::pending()->count())->toBe(1)
    ->and(SynonymSuggestion::approved()->count())->toBe(1);
});

test('it correctly filters by confidence score threshold', function () {
  SynonymSuggestion::factory()->create(['confidence_score' => 0.2]);
  SynonymSuggestion::factory()->create(['confidence_score' => 0.8]);

  // اختبار النطاق الافتراضي (0.5)
  expect(SynonymSuggestion::highConfidence()->count())->toBe(1);

  // اختبار Threshold مخصص
  expect(SynonymSuggestion::highConfidence(0.1)->count())->toBe(2);
});
