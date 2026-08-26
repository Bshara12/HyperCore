<?php

use App\Domains\Search\DTOs\UserPreferenceDTO;
use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Lexicon\ProjectSynonyms;
use App\Domains\Search\Support\Query\Extractors\LexicalAttributeExtractor;
use App\Domains\Search\Support\Query\Extractors\NegationExtractor;
use App\Domains\Search\Support\Query\Extractors\NumericExtractor;
use App\Domains\Search\Support\Query\Extractors\TemporalExtractor;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Ranking\Bm25fScorer;
use App\Domains\Search\Support\Ranking\CorpusStatistics;
use App\Domains\Search\Support\Ranking\PersonalizationScorer;
use App\Domains\Search\Support\Ranking\SignalScorer;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;

/**
 * صفّ فهرس مصطنع، بحقول مطبَّعة وأطوال محسوبة كما تفعل الفهرسة.
 */
function indexRow(array $overrides = []): object
{
    $title = $overrides['title'] ?? '';
    $content = $overrides['content'] ?? '';
    $meta = $overrides['meta'] ?? '';

    $titleFold = TextFolder::fold($title);
    $contentFold = TextFolder::fold($content);
    $metaFold = TextFolder::fold($meta);

    return (object) array_merge([
        'entry_id' => $overrides['entry_id'] ?? 1,
        'data_type_id' => 1,
        'data_type_slug' => $overrides['data_type_slug'] ?? 'products',
        'title' => $title,
        'content' => $content,
        'title_fold' => $titleFold,
        'content_fold' => $contentFold,
        'meta_fold' => $metaFold,
        'title_terms' => count(Segmenter::tokenize($titleFold)),
        'content_terms' => count(Segmenter::tokenize($contentFold)),
        'meta_terms' => count(Segmenter::tokenize($metaFold)),
        'click_count' => 0,
        'view_count' => 0,
        'popularity_score' => 0,
        'published_at' => null,
    ], array_diff_key($overrides, array_flip(['title', 'content', 'meta'])));
}

function analyzer(): QueryAnalyzer
{
    $lexicon = new Lexicon;

    return new QueryAnalyzer(
        $lexicon,
        new ProjectSynonyms,
        new NegationExtractor($lexicon),
        new TemporalExtractor($lexicon),
        new NumericExtractor($lexicon),
        new LexicalAttributeExtractor($lexicon),
    );
}

function stats(array $frequencies = [], int $docCount = 1000): CorpusStatistics
{
    return new CorpusStatistics(
        documentCount: $docCount,
        avgTitleTerms: 5.0,
        avgContentTerms: 100.0,
        avgMetaTerms: 5.0,
        documentFrequencies: $frequencies,
    );
}

/*
|--------------------------------------------------------------------------
| BM25F
|--------------------------------------------------------------------------
*/

it('يرجّح مطابقة العنوان على مطابقة المتن', function () {
    /*
     | هذه هي المعلومة التي كان MATCH() يهدرها: تسطيح العمودين في نصّ
     | واحد يجعل مطابقة العنوان تساوي مطابقة الحاشية.
     */
    $bm25 = new Bm25fScorer;
    $plan = analyzer()->analyze('titanium');
    $corpus = stats(['titanium' => 10]);

    $inTitle = $bm25->score($plan, indexRow(['title' => 'Titanium Frame']), $corpus);
    $inContent = $bm25->score($plan, indexRow([
        'title' => 'Some Product',
        'content' => 'the frame is made of titanium',
    ]), $corpus);

    expect($inTitle)->toBeGreaterThan($inContent);
});

it('يعطي الكلمة النادرة وزناً أعلى من الشائعة', function () {
    /*
     | هذا ما يجعل النظام يميّز "titanium" عن "the" بلا قائمة كلمات وقف
     | ولأي لغة كانت — إحصائياً لا بقائمة مكتوبة يدوياً.
     */
    $bm25 = new Bm25fScorer;
    $corpus = stats(['titanium' => 5, 'phone' => 900], 1000);

    $rare = $bm25->score(analyzer()->analyze('titanium'), indexRow(['title' => 'titanium']), $corpus);
    $common = $bm25->score(analyzer()->analyze('phone'), indexRow(['title' => 'phone']), $corpus);

    expect($rare)->toBeGreaterThan($common);
});

it('يطبّع طول المستند فلا يفوز الطويل بحكم طوله', function () {
    /*
     | بلا التطبيع تفوز المستندات الطويلة دائماً: فرصة ورود أي كلمة
     | فيها أكبر، لا لأنها أوثق صلة.
     */
    $bm25 = new Bm25fScorer;
    $plan = analyzer()->analyze('camera');
    $corpus = stats(['camera' => 50]);

    $focused = $bm25->score($plan, indexRow([
        'title' => 'Camera',
        'content' => 'a short review of the camera',
    ]), $corpus);

    $padded = $bm25->score($plan, indexRow([
        'title' => 'Camera',
        'content' => 'the camera '.str_repeat('filler words here ', 200),
    ]), $corpus);

    expect($focused)->toBeGreaterThan($padded);
});

it('يشبع التكرار فلا يفيد حشو الكلمات المفتاحية', function () {
    $bm25 = new Bm25fScorer;
    $plan = analyzer()->analyze('phone');
    $corpus = stats(['phone' => 100]);

    $once = $bm25->score($plan, indexRow(['title' => 'phone']), $corpus);
    $spammed = $bm25->score($plan, indexRow(['title' => str_repeat('phone ', 30)]), $corpus);

    // التكرار ثلاثين مرة لا يضاعف الدرجة ثلاثين ضعفاً.
    expect($spammed)->toBeLessThan($once * 3);
});

it('يعطي التوسعة نصف وزن المصطلح الأصلي', function () {
    $bm25 = new Bm25fScorer;
    $corpus = stats(['iphone' => 50, 'ايفون' => 50]);

    $plan = analyzer()->analyze('ايفون');

    expect($plan->expansions)->toContain('iphone');

    $matchesOriginal = $bm25->score($plan, indexRow(['title' => 'ايفون برو']), $corpus);
    $matchesExpansion = $bm25->score($plan, indexRow(['title' => 'iphone pro']), $corpus);

    expect($matchesOriginal)->toBeGreaterThan($matchesExpansion)
        ->and($matchesExpansion)->toBeGreaterThan(0.0);
});

it('يكافئ تجاور العبارة', function () {
    /*
     | BM25 يعدّ التكرارات ولا يرى الترتيب: مستند يذكر "iphone" في
     | مقدّمته و"pro" في خاتمته يتساوى عنده مع مستند عنوانه "iPhone Pro".
     */
    $bm25 = new Bm25fScorer;
    $plan = analyzer()->analyze('iphone pro');

    $adjacent = $bm25->phraseBonus($plan, indexRow(['title' => 'iphone pro max']));
    $scattered = $bm25->phraseBonus($plan, indexRow([
        'title' => 'iphone review',
        'content' => 'this is a pro level device',
    ]));

    expect($adjacent)->toBeGreaterThan($scattered);
});

/*
|--------------------------------------------------------------------------
| الإشارات
|--------------------------------------------------------------------------
*/

it('يفضّل الدليل الكبير على النسبة العالية بعيّنة صغيرة', function () {
    /*
     | الصيغة السابقة clicks/(views+1) كانت تعطي مستنداً ظهر مرّة ونُقر
     | مرّة قيمة 0.5 — أعلى مما يحصل عليه مستند ظهر ألف مرة ونُقر أربعمئة.
     | أضعف دليل ممكن كان يتفوّق على أقوى دليل ممكن.
     */
    $signals = new SignalScorer;
    $plan = analyzer()->analyze('phone');

    $flimsy = $signals->score($plan, indexRow(['click_count' => 1, 'view_count' => 1]));
    $solid = $signals->score($plan, indexRow(['click_count' => 400, 'view_count' => 1000]));

    expect($solid)->toBeGreaterThan($flimsy);
});

it('ينحلّ أثر الحداثة تدريجياً لا فجأة', function () {
    /*
     | الصيغة السابقة 1/(days+1) كانت تُبقي 12% فقط لمحتوى عمره أسبوع،
     | فتحوّل البحث فعلياً إلى ترتيب زمني.
     */
    $signals = new SignalScorer;
    $plan = analyzer()->analyze('news');

    $today = $signals->score($plan, indexRow(['published_at' => now()->toDateTimeString()]));
    $week = $signals->score($plan, indexRow(['published_at' => now()->subDays(7)->toDateTimeString()]));

    expect($week)->toBeLessThan($today)
        ->and($week)->toBeGreaterThan($today * 0.8);
});

it('يرجّح مطابقة الشرط المرجِّح دون إقصاء غير المطابق', function () {
    $signals = new SignalScorer;
    $plan = analyzer()->analyze('iphone 2020');

    // شرط مرجِّح لا قاطع: السنة المجرّدة قد تكون اسم موديل.
    expect($plan->softFilters())->not->toBeEmpty();

    $matching = $signals->score($plan, indexRow(), [
        'year' => [['value_text' => '2020', 'value_num' => 2020.0]],
    ]);

    $other = $signals->score($plan, indexRow(), [
        'year' => [['value_text' => '2023', 'value_num' => 2023.0]],
    ]);

    expect($matching)->toBeGreaterThan($other);
});

it('يرجّح نوع المحتوى الموافق للنية', function () {
    $signals = new SignalScorer;
    $plan = analyzer()->analyze('screen repair service');

    $service = $signals->score($plan, indexRow(['data_type_slug' => 'services']));
    $article = $signals->score($plan, indexRow(['data_type_slug' => 'articles']));

    expect($service)->toBeGreaterThan($article);
});

/*
|--------------------------------------------------------------------------
| التخصيص
|--------------------------------------------------------------------------
*/

it('يحدّ أثر التخصيص بالسقف المضبوط', function () {
    /*
     | الإصدار السابق كان يضيف termAffinity × 6.0 بلا سقف، فمع عشرين
     | مصطلحاً يبلغ السقف النظري 120 نقطة — أضعاف ما يمنحه أي تطابق
     | نصّي. أي أن التخصيص كان يلغي البحث لا يرجّحه.
     */
    config(['search.ranking.personalization.max_boost' => 0.25]);

    $scorer = new PersonalizationScorer;

    $preference = new UserPreferenceDTO(
        affinities: [1 => 0.9],
        termAffinities: ['iphone' => 0.9, 'pro' => 0.9],
        totalClicks: 50,
        hasHistory: true,
    );

    $base = 10.0;
    $boosted = $scorer->apply($base, indexRow(['title' => 'iphone pro']), $preference);

    expect($boosted)->toBeGreaterThan($base)
        ->and($boosted)->toBeLessThanOrEqual($base * 1.25);
});

it('لا يرفع نتيجة بلا صلة مهما وافقت ذوق المستخدم', function () {
    $scorer = new PersonalizationScorer;

    $preference = new UserPreferenceDTO(
        affinities: [1 => 0.95],
        termAffinities: ['iphone' => 0.95],
        totalClicks: 100,
        hasHistory: true,
    );

    // درجة أساسية صفر: المستند لا يطابق الاستعلام أصلاً.
    expect($scorer->apply(0.0, indexRow(['title' => 'iphone']), $preference))->toBe(0.0);
});

it('يضمحلّ وزن البحث القديم ولا ينمو', function () {
    /*
     | هذه هي العلّة المقلوبة: Carbon 3 تعيد diffInDays موقَّعة، فكان
     | العمر سالباً فينقلب exp(-age) إلى exp(+age):
     |
     |     بحث قبل 14 يوماً  → وزن سبعة أضعاف بحث اليوم
     |     بحث قبل 30 يوماً  → وزن أربعة وسبعين ضعفاً
     |
     | أي أن التخصيص كان يقدّم أقدم اهتمامات المستخدم على أحدثها.
     */
    config(['search.ranking.personalization.half_life_days' => 7.0]);

    $scorer = new PersonalizationScorer;
    $preference = new UserPreferenceDTO([], [], 10, true);
    $row = indexRow(['title' => 'iphone pro']);

    $fresh = $scorer->affinity($row, $preference, [['term' => 'iphone', 'age_days' => 0.0]]);
    $old = $scorer->affinity($row, $preference, [['term' => 'iphone', 'age_days' => 28.0]]);

    expect($fresh)->toBeGreaterThan($old)
        ->and($old)->toBeLessThan(0.1);
});

it('لا يخصّص لمستخدم بلا تاريخ', function () {
    $scorer = new PersonalizationScorer;

    expect($scorer->apply(10.0, indexRow(), UserPreferenceDTO::noHistory()))->toBe(10.0);
});
