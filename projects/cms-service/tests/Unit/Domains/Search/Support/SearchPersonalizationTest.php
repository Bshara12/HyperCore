<?php

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Support\KeywordTokenizer;
use App\Domains\Search\Support\SearchResultRanker;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use Illuminate\Support\Facades\Cache;

/**
 * تحقّق من مسار الشخصنة كاملاً:
 *
 *   user_click_logs → getClickedEntryTexts → KeywordTokenizer
 *   → termAffinities → SearchResultRanker boost
 *
 * الشرط الحاسم: الرموز على طرفَي المسار يجب أن تكون بنفس الشكل.
 * الـ termAffinities تُبنى من نص الفهرس، والـ ranker يُطابقها برموز
 * العنوان/المحتوى — أي اختلاف في التطبيع يُصفّر الشخصنة العربية صامتاً.
 */

/** نفس نص الـ seeder: ألف بمدّة في العنوان الخام */
const CLICKED_AR_TEXT = 'آيفون 15 برو ماكس - أفضل سعر. آيفون بشريحة A17 Pro وكاميرا 48 ميجابكسل.';

function preferenceAnalyzer(array $clickedTexts, array $clickCounts = []): UserPreferenceAnalyzer
{
    $repository = Mockery::mock(UserBehaviorRepositoryInterface::class);
    $repository->shouldReceive('getClickCountsByDataType')->andReturn($clickCounts);
    $repository->shouldReceive('getClickedEntryTexts')->andReturn($clickedTexts);

    return new UserPreferenceAnalyzer($repository, new KeywordTokenizer());
}

function rankerRow(array $attributes): object
{
    return (object) array_merge([
        'entry_id' => 1,
        'data_type_id' => 1,
        'data_type_slug' => 'products',
        'title' => '',
        'content' => '',
        'fulltext_score' => 1.0,
        'click_count' => 0,
        'view_count' => 0,
        'popularity_score' => 0,
        'ctr_score' => 0,
        'freshness_score' => 0,
    ], $attributes);
}

beforeEach(function () {
    Cache::flush();
});

// ─────────────────────────────────────────────────────────────────────
// بناء الـ termAffinities
// ─────────────────────────────────────────────────────────────────────

test('termAffinities تُبنى بمفاتيح مُطبَّعة كما يعرّفها UserPreferenceDTO', function () {
    $preference = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT])
        ->analyzeForUser(projectId: 1, userId: 7);

    expect($preference->hasHistory)->toBeTrue()
        // المفتاح يجب أن يكون الشكل المُطبَّع، لا "آيفون" الخام
        ->and($preference->termAffinities)->toHaveKey('ايفون')
        ->and($preference->termAffinities)->not->toHaveKey('آيفون');
});

test('الكلمات المحيّدة (price/best) لا تدخل الـ termAffinities', function () {
    $preference = preferenceAnalyzer([
        'iPhone 15 best price offer',
        'iPhone 14 best price deal',
    ])->analyzeForUser(projectId: 1, userId: 7);

    expect($preference->termAffinities)->toHaveKey('iphone')
        ->and($preference->termAffinities)->not->toHaveKey('price')
        ->and($preference->termAffinities)->not->toHaveKey('best');
});

// ─────────────────────────────────────────────────────────────────────
// تطبيق الـ boost في الترتيب
// ─────────────────────────────────────────────────────────────────────

test('boost الشخصنة يُطبَّق على النص العربي الخام في الفهرس', function () {
    $preference = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT])
        ->analyzeForUser(projectId: 1, userId: 7);

    $ranker = new SearchResultRanker(new KeywordTokenizer());

    $rows = $ranker->rerank(
        rows: [
            rankerRow(['entry_id' => 20, 'title' => 'سامسونج جالكسي S24']),
            rankerRow(['entry_id' => 10, 'title' => 'آيفون 15 برو ماكس']),
        ],
        cleanWords: [],
        phraseQuery: '',
        intent: 'general',
        intentConf: 0.0,
        preference: $preference,
    );

    // الصف الذي يطابق ذوق المستخدم يجب أن يتقدّم
    expect($rows[0]->entry_id)->toBe(10)
        ->and($rows[0]->final_score)->toBeGreaterThan($rows[1]->final_score);
});

test('boost الشخصنة يعمل حين تكون الكلمة المشتركة الوحيدة تحمل همزة', function () {
    // عزل الحالة: كل الرموز الأخرى مختلفة، والمشترك الوحيد هو "آيفون".
    // لو أُسقط هذا الرمز بسبب اختلاف التطبيع، لا يبقى أي boost.
    $preference = preferenceAnalyzer([
        'آيفون تيتانيوم',
        'آيفون سيراميك',
    ])->analyzeForUser(projectId: 1, userId: 7);

    $ranker = new SearchResultRanker(new KeywordTokenizer());

    $boosted = $ranker->rerank(
        rows: [rankerRow(['title' => 'أيفون بلس'])],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: $preference,
    );

    $baseline = $ranker->rerank(
        rows: [rankerRow(['title' => 'أيفون بلس'])],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: UserPreferenceDTO::noHistory(),
    );

    expect($boosted[0]->final_score)->toBeGreaterThan($baseline[0]->final_score);
});

test('boost الشخصنة لا يُطبَّق على صف غير مطابق', function () {
    $preference = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT])
        ->analyzeForUser(projectId: 1, userId: 7);

    $ranker = new SearchResultRanker(new KeywordTokenizer());

    $withHistory = $ranker->rerank(
        rows: [rankerRow(['title' => 'سامسونج جالكسي S24'])],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: $preference,
    );

    $withoutHistory = $ranker->rerank(
        rows: [rankerRow(['title' => 'سامسونج جالكسي S24'])],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: UserPreferenceDTO::noHistory(),
    );

    expect($withHistory[0]->final_score)->toBe($withoutHistory[0]->final_score);
});

test('affinity نوع البيانات يُرفع الصفوف من النوع المُفضَّل', function () {
    $preference = preferenceAnalyzer([], [1 => 10, 2 => 1])
        ->analyzeForUser(projectId: 1, userId: 7);

    expect($preference->affinityFor(1))->toBeGreaterThan(0.0)
        // نوع بنقرة واحدة تحت MIN_CLICKS_FOR_SIGNAL → لا إشارة
        ->and($preference->affinityFor(2))->toBe(0.0);

    $ranker = new SearchResultRanker(new KeywordTokenizer());

    $rows = $ranker->rerank(
        rows: [
            rankerRow(['entry_id' => 30, 'data_type_id' => 2, 'title' => 'مقال عن التصوير']),
            rankerRow(['entry_id' => 31, 'data_type_id' => 1, 'title' => 'كاميرا كانون']),
        ],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: $preference,
    );

    expect($rows[0]->entry_id)->toBe(31);
});

test('كلمات بحث المستخدم السابقة تُرفع الصف حتى لو كُتبت بهمزة مختلفة', function () {
    $ranker = new SearchResultRanker(new KeywordTokenizer());

    $rows = $ranker->rerank(
        rows: [
            rankerRow(['entry_id' => 40, 'title' => 'سامسونج جالكسي S24']),
            rankerRow(['entry_id' => 41, 'title' => 'آيفون 15 برو ماكس']),
        ],
        cleanWords: [], phraseQuery: '', intent: 'general', intentConf: 0.0,
        preference: UserPreferenceDTO::noHistory(),
        // المستخدم بحث سابقاً بـ "أيفون" (همزة على الألف)
        userKeywords: [['word' => 'أيفون', 'weight' => 1.0]],
    );

    expect($rows[0]->entry_id)->toBe(41);
});

// ─────────────────────────────────────────────────────────────────────
// الـ cache
// ─────────────────────────────────────────────────────────────────────

test('التفضيلات تُخزَّن مؤقتاً وتُبطَل عند نقرة جديدة', function () {
    $analyzer = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT]);

    $first = $analyzer->analyzeForUser(projectId: 1, userId: 7);
    expect($analyzer->analyzeForUser(projectId: 1, userId: 7))->toEqual($first);

    $analyzer->invalidateCache(projectId: 1, userId: 7);

    expect($analyzer->analyzeForUser(projectId: 1, userId: 7))->toEqual($first);
});
