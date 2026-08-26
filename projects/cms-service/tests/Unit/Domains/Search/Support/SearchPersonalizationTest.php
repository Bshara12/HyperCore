<?php

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Repositories\Interfaces\UserBehaviorRepositoryInterface;
use App\Domains\Search\Support\Ranking\PersonalizationScorer;
use App\Domains\Search\Support\Text\TextFolder;
use App\Domains\Search\Support\UserPreferenceAnalyzer;
use Illuminate\Support\Facades\Cache;

/**
 * سلسلة التخصيص من طرفها إلى طرفها:
 *
 *   user_click_logs → getClickedEntryTexts → تطبيع وتقسيم
 *   → termAffinities → مضاعِف الترتيب
 *
 * تُغطّي هذه الحالات ما كان يُختبَر على الواجهة السابقة
 * (SearchResultRanker + KeywordTokenizer)، منقولةً إلى المُسجِّل الحالي.
 * السلوكيات المطلوبة لم تتغيّر — تغيّر من ينفّذها.
 */
const CLICKED_AR_TEXT = 'آيفون 15 برو ماكس';

function preferenceAnalyzer(array $clickedTexts, array $clickCounts = []): UserPreferenceAnalyzer
{
    $repository = Mockery::mock(UserBehaviorRepositoryInterface::class);
    $repository->shouldReceive('getClickCountsByDataType')->andReturn($clickCounts);
    $repository->shouldReceive('getClickedEntryTexts')->andReturn($clickedTexts);

    return new UserPreferenceAnalyzer($repository);
}

/**
 * صفّ فهرس مصطنع.
 *
 * العنوان يُمرَّر خاماً ويُطوى هنا: المُسجِّل يطابق على title_fold،
 * فصفٌّ بلا طيّ لا يُنتج أي إشارة مهما كان عنوانه.
 */
function rankerRow(array $attributes): object
{
    $title = (string) ($attributes['title'] ?? '');

    return (object) array_merge([
        'entry_id' => 1,
        'data_type_id' => 1,
        'data_type_slug' => 'products',
        'title' => $title,
        'title_fold' => TextFolder::fold($title),
        'content_fold' => '',
        'meta_fold' => '',
        'title_terms' => 1,
        'content_terms' => 0,
        'meta_terms' => 0,
        'click_count' => 0,
        'view_count' => 0,
        'popularity_score' => 0,
        'published_at' => null,
    ], $attributes);
}

/** الدرجة النهائية بعد التخصيص، انطلاقاً من درجة أساسية ثابتة. */
function personalised(object $row, UserPreferenceDTO $preference, array $recentTerms = []): float
{
    return (new PersonalizationScorer)->apply(10.0, $row, $preference, $recentTerms);
}

beforeEach(function () {
    Cache::flush();
});

/*
|--------------------------------------------------------------------------
| بناء الـ termAffinities
|--------------------------------------------------------------------------
*/

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

/*
|--------------------------------------------------------------------------
| تطبيق المضاعِف
|--------------------------------------------------------------------------
*/

test('التخصيص يرفع الصف الموافق لذوق المستخدم فوق غيره', function () {
    $preference = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT])
        ->analyzeForUser(projectId: 1, userId: 7);

    $matching = personalised(rankerRow(['title' => 'آيفون 15 برو ماكس']), $preference);
    $other = personalised(rankerRow(['title' => 'سامسونج جالكسي S24']), $preference);

    expect($matching)->toBeGreaterThan($other);
});

test('التخصيص يعمل حين تكون الكلمة المشتركة الوحيدة تحمل همزة', function () {
    /*
     | عزل الحالة: كل الرموز الأخرى مختلفة، والمشترك الوحيد "آيفون"
     | مقابل "أيفون". لو أُسقط بسبب اختلاف صورة الهمزة لما بقي أي أثر.
     */
    $preference = preferenceAnalyzer([
        'آيفون تيتانيوم',
        'آيفون سيراميك',
    ])->analyzeForUser(projectId: 1, userId: 7);

    $boosted = personalised(rankerRow(['title' => 'أيفون بلس']), $preference);
    $baseline = personalised(rankerRow(['title' => 'أيفون بلس']), UserPreferenceDTO::noHistory());

    expect($boosted)->toBeGreaterThan($baseline);
});

test('التخصيص لا يُطبَّق على صف غير مطابق ولا يخصّ نوعه', function () {
    /*
     | بلا ميل للنوع ولا تطابق مفردات، يجب أن تعود الدرجة كما هي —
     | التخصيص يرجّح المطابق ولا يرفع الجميع بالقدر نفسه.
     */
    $preference = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT])
        ->analyzeForUser(projectId: 1, userId: 7);

    $row = rankerRow(['title' => 'سامسونج جالكسي S24', 'data_type_id' => 99]);

    expect(personalised($row, $preference))
        ->toBe(personalised($row, UserPreferenceDTO::noHistory()));
});

test('affinity نوع البيانات يرفع الصفوف من النوع المُفضَّل', function () {
    $preference = preferenceAnalyzer([], [1 => 10, 2 => 1])
        ->analyzeForUser(projectId: 1, userId: 7);

    expect($preference->affinityFor(1))->toBeGreaterThan(0.0)
        // نوع بنقرة واحدة تحت MIN_CLICKS_FOR_SIGNAL → لا إشارة
        ->and($preference->affinityFor(2))->toBe(0.0);

    $preferred = personalised(rankerRow(['data_type_id' => 1, 'title' => 'كاميرا كانون']), $preference);
    $other = personalised(rankerRow(['data_type_id' => 2, 'title' => 'مقال عن التصوير']), $preference);

    expect($preferred)->toBeGreaterThan($other);
});

test('كلمات بحث المستخدم السابقة ترفع الصف حتى لو كُتبت بهمزة مختلفة', function () {
    // بحث سابق بـ "أيفون" (همزة على الألف) مقابل عنوان بـ "آيفون".
    $recent = [['term' => TextFolder::fold('أيفون'), 'age_days' => 0.0]];

    $matching = personalised(
        rankerRow(['title' => 'آيفون 15 برو ماكس']),
        new UserPreferenceDTO([], [], 5, true),
        $recent
    );

    $other = personalised(
        rankerRow(['title' => 'سامسونج جالكسي S24']),
        new UserPreferenceDTO([], [], 5, true),
        $recent
    );

    expect($matching)->toBeGreaterThan($other);
});

/*
|--------------------------------------------------------------------------
| الكاش
|--------------------------------------------------------------------------
*/

test('التفضيلات تُخزَّن مؤقتاً وتُبطَل عند نقرة جديدة', function () {
    $analyzer = preferenceAnalyzer([CLICKED_AR_TEXT, CLICKED_AR_TEXT]);

    $first = $analyzer->analyzeForUser(projectId: 1, userId: 7);
    expect($analyzer->analyzeForUser(projectId: 1, userId: 7))->toEqual($first);

    $analyzer->invalidateCache(projectId: 1, userId: 7);

    expect($analyzer->analyzeForUser(projectId: 1, userId: 7))->toEqual($first);
});
