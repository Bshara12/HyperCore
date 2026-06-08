<?php

use App\Domains\Search\DTOs\SynonymAnalysisResultDTO;
use App\Domains\Search\DTOs\SynonymSuggestionDTO;

test('it initializes properties correctly', function () {
  $dto = new SynonymAnalysisResultDTO(
    projectId: 5,
    keywordsAnalyzed: 500,
    uniqueWordsFound: 200,
    pairsEvaluated: 1000,
    suggestionsGenerated: 25,
    suggestions: [],
    durationMs: 120.556
  );

  expect($dto->projectId)->toBe(5)
    ->and($dto->keywordsAnalyzed)->toBe(500)
    ->and($dto->durationMs)->toBe(120.556);
});

test('toArray handles rounding and maps nested suggestions correctly', function () {
  // استخدم الكلاس الحقيقي بدلاً من Mockery
  $suggestion = new SynonymSuggestionDTO(
    wordA: 'iphone',
    wordB: 'smartphone',
    jaccardScore: 0.85432,      // سيتم تقريبه لـ 0.8543
    cooccurrenceCount: 50,
    confidenceScore: 0.923456,  // سيتم تقريبه لـ 0.9235
    wordACount: 100,
    wordBCount: 100,
    language: 'en'
  );

  $dto = new SynonymAnalysisResultDTO(
    projectId: 1,
    keywordsAnalyzed: 10,
    uniqueWordsFound: 5,
    pairsEvaluated: 10,
    suggestionsGenerated: 1,
    suggestions: [$suggestion],
    durationMs: 12.344 // سيتم تقريبه لـ 12.34
  );

  $array = $dto->toArray();

  // التحقق من القيم
  expect($array['suggestions'][0])->toBe([
    'word_a' => 'iphone',
    'word_b' => 'smartphone',
    'jaccard_score' => 0.8543,
    'cooccurrence_count' => 50,
    'confidence_score' => 0.9235,
  ])
    ->and($array['duration_ms'])->toBe(12.34)
    ->and($array['project_id'])->toBe(1);
});

test('it normalizes order when wordA is alphabetically greater than wordB', function () {
  // هنا wordA (zebra) تأتي بعد wordB (apple) أبجدياً، لذا يجب أن يتبادلا الأماكن
  $dto = SynonymSuggestionDTO::normalized(
    wordA: 'zebra',
    wordB: 'apple',
    jaccardScore: 0.5,
    cooccurrenceCount: 10,
    confidenceScore: 0.8,
    wordACount: 100, // عدد مرات ظهور zebra
    wordBCount: 200, // عدد مرات ظهور apple
    language: 'en'
  );

  // التحقق من التبديل
  expect($dto->wordA)->toBe('apple')
    ->and($dto->wordB)->toBe('zebra')
    // التأكد من أن الأعداد قد تبدلت أيضاً لتطابق الكلمات
    ->and($dto->wordACount)->toBe(200)
    ->and($dto->wordBCount)->toBe(100);
});

test('it keeps order when wordA is alphabetically smaller than wordB', function () {
  // هنا الترتيب صحيح أصلاً (apple قبل zebra)، لذا لا يجب أن يحدث تبديل
  $dto = SynonymSuggestionDTO::normalized(
    wordA: 'apple',
    wordB: 'zebra',
    jaccardScore: 0.5,
    cooccurrenceCount: 10,
    confidenceScore: 0.8,
    wordACount: 200,
    wordBCount: 100,
    language: 'en'
  );

  expect($dto->wordA)->toBe('apple')
    ->and($dto->wordB)->toBe('zebra')
    ->and($dto->wordACount)->toBe(200)
    ->and($dto->wordBCount)->toBe(100);
});
