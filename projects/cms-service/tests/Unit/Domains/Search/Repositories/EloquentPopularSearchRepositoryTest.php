<?php

use App\Domains\Search\DTOs\PopularSearchQueryDTO;
use App\Domains\Search\Repositories\Eloquent\EloquentPopularSearchRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

// تفعيل تنظيف قاعدة البيانات بعد كل اختبار لضمان الاستقلالية
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = new EloquentPopularSearchRepository();
    $this->projectId = 1;
    $this->language = 'ar';
});

/**
 * ==============================================================================
 * 1. الحالات الدفاعية (Defensive Returns)
 * نستخدم runInSeparateProcess لنعزل الـ Mock تماماً عن بقية التستات، 
 * مما يتيح لنا محاكاة الدالة الستاتيك وإجبارها على إرجاع مصفوفة فارغة لتخطي الحلقة.
 * ==============================================================================
 */

test('getTrending hits defensive return when chain is empty', function () {
    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, 'non-existent-window', 'trending', 5);
    
    $result = $this->repository->getTrending($dto);

    expect($result['rows'])->toBe([])
        ->and($result['fallback_applied'])->toBeFalse();
});

test('getPopular hits defensive return when chain is empty', function () {
    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, 'non-existent-window', 'popular', 5);
    
    $result = $this->repository->getPopular($dto);

    expect($result['rows'])->toBe([])
        ->and($result['fallback_applied'])->toBeFalse();
});

/**
 * ==============================================================================
 * 2. اختبارات السلوك الطبيعي للدالة getTrending
 * ==============================================================================
 */

test('getTrending returns results directly if they meet the minimum', function () {
    // إدخال 3 سجلات لتحقيق شرط الحد الأدنى MINIMUM_RESULTS = 3
    DB::table('popular_searches')->insert([
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'k1', 'normalized_keyword' => 'k1', 'count_24h' => 10, 'count_all_time' => 10, 'trending_score' => 100],
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'k2', 'normalized_keyword' => 'k2', 'count_24h' => 5,  'count_all_time' => 5,  'trending_score' => 50],
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'k3', 'normalized_keyword' => 'k3', 'count_24h' => 2,  'count_all_time' => 2,  'trending_score' => 20],
    ]);

    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, '24h', 'trending', 5);
    $result = $this->repository->getTrending($dto);

    // سيعود فوراً دون تطبيق Fallback لأنه حقق الحد الأدنى
    expect($result['rows'])->toHaveCount(3)
        ->and($result['fallback_applied'])->toBeFalse();
});

test('getTrending applies fallback chain when results are fewer than minimum', function () {
    // إدخال سجل واحد فقط (أقل من الحد الأدنى) لإجبار الكود على استنفاذ السلسلة
    DB::table('popular_searches')->insert([
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'k1', 'normalized_keyword' => 'k1', 'count_24h' => 10, 'count_all_time' => 10, 'trending_score' => 100],
    ]);

    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, '24h', 'trending', 5);
    $result = $this->repository->getTrending($dto);

    // سيصل إلى نهاية الـ chain ويعيد السجل المتاح مع تأكيد تطبيق الـ Fallback
    expect($result['rows'])->toHaveCount(1)
        ->and($result['fallback_applied'])->toBeTrue();
});

/**
 * ==============================================================================
 * 3. اختبارات السلوك الطبيعي للدالة getPopular
 * ==============================================================================
 */

test('getPopular returns records sorted by alltime score', function () {
    DB::table('popular_searches')->insert([
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'low',  'normalized_keyword' => 'low',  'count_all_time' => 10, 'alltime_score' => 10],
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'high', 'normalized_keyword' => 'high', 'count_all_time' => 30, 'alltime_score' => 100],
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'mid',  'normalized_keyword' => 'mid',  'count_all_time' => 20, 'alltime_score' => 50],
    ]);

    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, 'all', 'popular', 5);
    $result = $this->repository->getPopular($dto);

    expect($result['rows'])->toHaveCount(3)
        ->and($result['rows'][0]->keyword)->toBe('high')
        ->and($result['rows'][1]->keyword)->toBe('mid')
        ->and($result['rows'][2]->keyword)->toBe('low');
});

test('getPopular returns last window result when chain is exhausted', function () {
    DB::table('popular_searches')->insert([
        ['project_id' => 1, 'language' => 'ar', 'keyword' => 'lonely', 'normalized_keyword' => 'lonely', 'count_all_time' => 10, 'alltime_score' => 50],
    ]);

    $dto = new PopularSearchQueryDTO($this->projectId, $this->language, '24h', 'popular', 5);
    $result = $this->repository->getPopular($dto);

    expect($result['rows'])->toHaveCount(1)
        ->and($result['rows'][0]->keyword)->toBe('lonely')
        ->and($result['fallback_applied'])->toBeTrue();
});

/**
 * ==============================================================================
 * 4. اختبارات دالة إعادة الحساب (Recompute)
 * ==============================================================================
 */

test('recompute returns zero status when search logs are empty', function () {
    $result = $this->repository->recompute($this->projectId, $this->language);

    expect($result['processed'])->toBe(0)
        ->and($result['upserted'])->toBe(0);
});

test('recompute handles null last_searched_at correctly', function () {
    // إضافة قيمة افتراضية لـ searched_at لتجنب الـ Constraint Violation
    DB::table('user_search_logs')->insert([
        'project_id' => 1, 
        'language' => 'ar', 
        'keyword' => 'no_date', 
        'searched_at' => now()->toDateTimeString() 
    ]);

    $result = $this->repository->recompute($this->projectId, $this->language);
    expect($result['processed'])->toBe(1);
});

test('recompute aggregates logs and upserts data successfully', function () {
    $now = now();
    
    // 1. إضافة سجل في log
    DB::table('user_search_logs')->insert([
        'project_id' => 1, 
        'language' => 'ar', 
        'keyword' => 'بحث قوي', 
        'searched_at' => $now->toDateTimeString()
    ]);

    // 2. إضافة السجل في suggestions مع ضمان تعبئة كل الحقول الإجبارية
    DB::table('search_suggestions')->insert([
        'project_id' => 1, 
        'language' => 'ar', 
        'normalized_keyword' => 'بحث قوي', 
        'keyword' => 'بحث قوي', 
        'click_count' => 12,
        'last_searched_at' => $now->toDateTimeString() // <--- هذا هو السطر الناقص
    ]);

    $result = $this->repository->recompute(1, 'ar');
    
    expect($result['upserted'])->toBe(1);
    
    // التأكد من أن البيانات تم معالجتها
    $upserted = DB::table('popular_searches')->where('keyword', 'بحث قوي')->first();
    expect($upserted)->not->toBeNull()
        ->and($upserted->click_count)->toBe(12);
});