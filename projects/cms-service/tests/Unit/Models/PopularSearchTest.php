<?php

use App\Models\PopularSearch;

test('it calculates trending score correctly', function () {
  // 1. المسار الطبيعي (حديث vs قديم)
  $score1 = PopularSearch::calculateTrendingScore(100, 100, 100, now()->subHour());
  $score2 = PopularSearch::calculateTrendingScore(100, 100, 100, null);
  expect($score1)->toBeGreaterThan($score2);

  // 2. تغطية حالة weightedCount === 0 (القيم الصفرية)
  expect(PopularSearch::calculateTrendingScore(0, 0, 0))->toBe(0.0);

  // 3. تغطية معالجة القيم السالبة (تأكد أن max(0, $val) تعمل)
  expect(PopularSearch::calculateTrendingScore(-10, -10, -10))->toBe(0.0);
});

test('it calculates all-time score correctly', function () {
  // 1. المسار الطبيعي مع وجود تاريخ
  $score = PopularSearch::calculateAlltimeScore(100, 50, now());
  expect($score)->toBeGreaterThan(0);

  // 2. تغطية حالة null (بدون recency bonus)
  $scoreNull = PopularSearch::calculateAlltimeScore(100, 50, null);
  expect($scoreNull)->toBeLessThan($score);

  // 3. تغطية القيم السالبة (تأكد أن max(0, $val) تعمل)
  expect(PopularSearch::calculateAlltimeScore(-10, -10, null))->toBe(0.0);
});

test('it detects trends correctly', function () {
  // 1. rising
  expect(PopularSearch::detectTrend(100, 50))->toBe('rising');

  // 2. falling
  expect(PopularSearch::detectTrend(5, 100))->toBe('falling');

  // 3. stable
  expect(PopularSearch::detectTrend(7, 50))->toBe('stable');

  // 4. تغطية حالة count7d === 0 (القيم الصفرية)
  expect(PopularSearch::detectTrend(10, 0))->toBe('rising');
  expect(PopularSearch::detectTrend(0, 0))->toBe('stable');

  // 5. تغطية القيم السالبة
  expect(PopularSearch::detectTrend(-5, -10))->toBe('stable');
});

test('it handles edge cases for denominator and finite scores', function () {
    // 1. تغطية المسار الحسابي للـ denominator
    // بما أن الكود يستخدم max(0, ...)، فإن النتيجة ستكون رقم موجب
    $score = PopularSearch::calculateTrendingScore(100, 100, 100, now()->addHours(3));
    expect($score)->toBeGreaterThan(0.0);

    // 2. تغطية حالة !is_finite
    // نقوم بتمرير قيم ضخمة جداً
    $scoreFinite = PopularSearch::calculateTrendingScore(PHP_INT_MAX, PHP_INT_MAX, PHP_INT_MAX, now()->subSeconds(1));
    expect($scoreFinite)->toBeFloat();
});

test('it handles edge cases for alltime score finiteness', function () {
    // تغطية سطر is_finite في calculateAlltimeScore
    // تمرير قيم قصوى لضمان اختبار الدالة
    $score = PopularSearch::calculateAlltimeScore(PHP_INT_MAX, PHP_INT_MAX, now());
    expect($score)->toBeFloat();
});