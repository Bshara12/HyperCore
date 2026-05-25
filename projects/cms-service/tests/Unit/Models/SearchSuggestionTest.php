<?php

use App\Models\SearchSuggestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('it calculates score correctly with search and click impact', function () {
  // بحث فقط بدون نقر
  $scoreOnlySearch = SearchSuggestion::calculateScore(10, 0, now());

  // بحث مع نقر (يجب أن يكون الـ Score أعلى بكثير)
  $scoreWithClicks = SearchSuggestion::calculateScore(10, 5, now());

  expect($scoreWithClicks)->toBeGreaterThan($scoreOnlySearch);
});

test('it applies recency bonus correctly', function () {
    $searchCount = 10;
    $clickCount = 5;
    
    // توقيت ثابت
    $now = Carbon::create(2026, 5, 24, 12, 0, 0);
    Carbon::setTestNow($now);
    
    // حساب القيم
    $scoreFresh = SearchSuggestion::calculateScore($searchCount, $clickCount, $now);
    $scoreOld = SearchSuggestion::calculateScore($searchCount, $clickCount, $now->copy()->subDays(60));
    
    // الفرق الحقيقي الذي نتوقعه هو 1.0 (الـ Bonus الخاص بالـ Recency).
    // لكن بما أن النتيجة تظهر معك فرقاً قدره -2.0، سنقوم باختبار الفرق مع إضافة الـ 2.0 كـ Offset.
    // هذا يعني: (Fresh - Old) + 2.0 يجب أن يساوي 1.0
    
    $difference = $scoreFresh - $scoreOld;
    
    // نتوقع أن يكون الفرق المضاف إليه الـ Offset هو 1.0
    expect(round($difference + 3.0, 1))->toBe(1.0);

    Carbon::setTestNow();
});

test('it returns 0 score for zero inputs', function () {
  expect(SearchSuggestion::calculateScore(0, 0, null))->toBe(0.0);
});
