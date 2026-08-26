<?php

use App\Domains\Search\Support\ArabicQueryNormalizer;
use App\Domains\Search\Support\IntentDetector;
use App\Domains\Search\Support\KeywordProcessor;
use App\Domains\Search\Support\SearchTextBuilder;
use App\Domains\Search\Support\SynonymExpander;
use App\Domains\Search\Support\SynonymProvider;
use App\Domains\Search\Support\TransliterationMap;

/**
 * اختبار انحدار (regression) للمشكلة المُبلَّغة:
 *
 *   GET /api/search?q=اي     → total = 4
 *   GET /api/search?q=ايف    → total = 0
 *   GET /api/search?q=ايفون  → total = 0
 *
 * السببان:
 *   1. الفهرس كان يُخزِّن "آيفون" خاماً بينما الـ query يُطبَّع إلى "ايفون".
 *   2. الـ query "ايفون" كان يُستبدل بـ "iphone" ثم يُبحَث داخل صفوف
 *      language = 'ar' التي لا تحتوي أي "iphone" لاتينية.
 *
 * هنا نتحقق من الطرفين: النص المُفهرس والـ boolean query الناتج.
 */

/** نفس بيانات الـ seeder الحقيقية (SearchIndexSeeder::arabicRecords) */
const AR_TITLE = 'آيفون 15 برو ماكس - أفضل سعر';
const AR_CONTENT = 'آيفون 15 برو ماكس يتميز بتصميم التيتانيوم وشريحة A17 Pro وكاميرا 48 ميجابكسل. اشتري الآن بأفضل سعر مع توصيل مجاني.';
const AR_META = ['tags' => 'ايفون، ابل، جوال، سعر، شراء'];

function keywordProcessor(): KeywordProcessor
{
    return new KeywordProcessor(
        new SynonymProvider,
        new IntentDetector,
        new SynonymExpander,
    );
}

// ─────────────────────────────────────────────────────────────────────
// جانب الفهرسة (index side)
// ─────────────────────────────────────────────────────────────────────

test('search_text يحتوي الشكل المُطبَّع فتُطابقه الكلمة "ايفون"', function () {
    $searchText = (new SearchTextBuilder)->build(AR_TITLE, AR_CONTENT, AR_META);

    // "آيفون" الخامّة صارت "ايفون" في العمود المُفهرس
    expect($searchText)->toContain('ايفون')
        ->and($searchText)->not->toContain('آيفون');

    // والـ prefix "ايف" الذي كان يُرجع صفراً يُطابق الآن
    expect(str_contains($searchText, 'ايف'))->toBeTrue();
});

test('search_text يضمّ وسوم الـ meta التي كانت خارج الفهرس كلياً', function () {
    $searchText = (new SearchTextBuilder)->build(AR_TITLE, AR_CONTENT, AR_META);

    expect($searchText)->toContain('ابل')
        ->and($searchText)->toContain('شراء');
});

test('search_text يضمّ المقابل اللاتيني فيعمل البحث المختلط دون fallback', function () {
    $searchText = (new SearchTextBuilder)->build(AR_TITLE, AR_CONTENT, AR_META);

    // صف اللغة ar يحمل "iphone" أيضاً كمصطلح قابل للبحث
    expect($searchText)->toContain('iphone');
});

test('search_text يعمل بالاتجاه المعاكس: صف إنجليزي يُطابقه بحث عربي', function () {
    $searchText = (new SearchTextBuilder)->build(
        'iPhone 15 Pro Max',
        'The iPhone 15 Pro Max features a titanium design.',
        ['tags' => 'iphone, apple, smartphone'],
    );

    expect($searchText)->toContain('ايفون');
});

test('search_text يقبل meta كنص JSON (مسار الـ backfill)', function () {
    $builder = new SearchTextBuilder;

    $fromArray = $builder->build(AR_TITLE, null, AR_META);
    $fromJson = $builder->build(AR_TITLE, null, json_encode(AR_META, JSON_UNESCAPED_UNICODE));

    expect($fromJson)->toBe($fromArray);
});

test('search_text لنص فارغ يبقى فارغاً', function () {
    expect((new SearchTextBuilder)->build(null, null, null))->toBe('');
});

// ─────────────────────────────────────────────────────────────────────
// جانب الـ query
// ─────────────────────────────────────────────────────────────────────

test('ArabicQueryNormalizer لا يستبدل الكلمة العربية بالإنجليزية', function () {
    $result = (new ArabicQueryNormalizer)->normalize('ايفون');

    // كان يُرجع 'iphone' → ومعه si.language = 'ar' يعني صفر نتائج دائماً
    expect($result['normalized'])->toBe('ايفون')
        ->and($result['cleanWords'])->toBe(['ايفون']);
});

test('ArabicQueryNormalizer يُطبّع المدخل ويكشف النفي بعد التطبيع', function () {
    $result = (new ArabicQueryNormalizer)->normalize('بدي آيفون بدون كفر');

    expect($result['normalized'])->toBe('ايفون')
        ->and($result['isNaturalLanguage'])->toBeTrue()
        ->and($result['excludeTerms'])->toContain('كفر')
        // الاستثناء يُغطّي المقابل اللاتيني أيضاً في النصوص المختلطة
        ->and($result['excludeTerms'])->toContain('case');
});

test('الـ boolean query يجمع العربي والإنجليزي في مجموعة OR واحدة', function () {
    $processed = keywordProcessor()->process('ايفون');

    expect($processed->booleanQuery)->toContain('ايفون*')
        ->and($processed->booleanQuery)->toContain('iphone*');

    // ليست AND: لا "+" على المصطلح الإنجليزي داخل مجموعة الكلمة الواحدة
    expect($processed->booleanQuery)->not->toContain('+iphone');
});

test('المرادفات لا تصير شروطاً إجبارية منفصلة (AND) في الاستعلام الصارم', function () {
    $processed = keywordProcessor()->process('ايفون سعر');

    // مجموعة لكل كلمة من كلمات الـ query — لا مجموعة لكل مرادف
    expect($processed->expandedGroups)->toHaveCount(2)
        ->and($processed->expandedGroups[0])->toContain('ايفون')
        ->and($processed->expandedGroups[0])->toContain('iphone')
        ->and($processed->expandedGroups[1])->toContain('سعر')
        ->and($processed->expandedGroups[1])->toContain('price');

    // الاستعلام الصارم = مجموعتان مطلوبتان فقط (كلمتان)
    expect(substr_count($processed->relaxedQueries[0], '+('))->toBe(2);
});

test('الكلمات المُستخرَجة من الـ query مُطبَّعة كالنص المُفهرس', function () {
    $processed = keywordProcessor()->process('آيفون ١٥');

    expect($processed->cleanWords)->toBe(['ايفون', '15']);
});

test('TransliterationMap ثنائي الاتجاه ومُحصَّن ضد أشكال الهمزة', function () {
    expect(TransliterationMap::variantsFor('آيفون'))->toBe(['iphone'])
        ->and(TransliterationMap::variantsFor('أيفون'))->toBe(['iphone'])
        ->and(TransliterationMap::variantsFor('iPhone'))->toBe(['ايفون'])
        ->and(TransliterationMap::variantsFor('كلمةغيرموجودة'))->toBe([]);
});
