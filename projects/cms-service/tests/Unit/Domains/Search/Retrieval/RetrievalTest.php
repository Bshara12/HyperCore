<?php

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Lexicon\ProjectSynonyms;
use App\Domains\Search\Support\Query\Extractors\LexicalAttributeExtractor;
use App\Domains\Search\Support\Query\Extractors\NegationExtractor;
use App\Domains\Search\Support\Query\Extractors\NumericExtractor;
use App\Domains\Search\Support\Query\Extractors\TemporalExtractor;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Retrieval\BooleanQueryBuilder;
use App\Domains\Search\Support\Retrieval\CandidateWindow;

beforeEach(function () {
    $lexicon = new Lexicon;

    $this->analyzer = new QueryAnalyzer(
        $lexicon,
        new ProjectSynonyms,
        new NegationExtractor($lexicon),
        new TemporalExtractor($lexicon),
        new NumericExtractor($lexicon),
        new LexicalAttributeExtractor($lexicon),
    );

    $this->builder = new BooleanQueryBuilder;

    config([
        'search.retrieval.candidate_multiplier' => 4,
        'search.retrieval.min_candidates' => 200,
        'search.retrieval.max_candidates' => 1000,
    ]);
});

/*
|--------------------------------------------------------------------------
| نافذة المرشَّحين — علّة الترقيم
|--------------------------------------------------------------------------
*/

it('تغطّي النافذة الصفحة المطلوبة دائماً', function (int $page, int $perPage) {
    /*
     | هذه هي العلّة الأصلية: كان الاستعلام ينتهي بـ
     |
     |     LIMIT ? OFFSET ?   bindings: [$fetchLimit, 0]
     |
     | بإزاحة صفر ثابتة، ثم يُقتطع من المئة صفّ المسحوبة بـ array_slice.
     | فما دامت الصفحة ضمن المئة الأولى يعمل الأمر، وما إن تتجاوزها حتى
     | تعود صفحات فارغة بينما يعلن total وجود مئات النتائج.
     */
    $window = CandidateWindow::forPage($page, $perPage);
    $lastNeededIndex = ($page * $perPage) - 1;

    if ($window->rerank) {
        expect($window->sliceOffset + $perPage)->toBeLessThanOrEqual($window->size)
            ->and($lastNeededIndex)->toBeLessThan($window->size);
    } else {
        // الترقيم العميق: صفحة واحدة بإزاحة حقيقية في SQL.
        expect($window->sqlOffset)->toBe(($page - 1) * $perPage)
            ->and($window->size)->toBe($perPage);
    }
})->with([
    [1, 15], [2, 15], [7, 15], [8, 15], [20, 15], [50, 15],
    [1, 50], [10, 50], [100, 10], [500, 20],
]);

it('تعيد الصفحة الثامنة نتائج لا فراغاً', function () {
    /*
     | الصفحة الثامنة بحجم 15 تبدأ عند العنصر 105 — أي خارج نافذة
     | الـ 100 الثابتة في الإصدار السابق تماماً. وهذا يعني أن كل بحث
     | بأكثر من سبع صفحات كان مكسوراً بصمت.
     */
    $window = CandidateWindow::forPage(8, 15);
    $ranked = array_map(static fn (int $i): object => (object) ['entry_id' => $i], range(1, $window->size));

    $page = $window->slice($ranked, 15);

    expect($page)->toHaveCount(15)
        ->and($page[0]->entry_id)->toBe(106);
});

it('لا تتداخل الصفحات المتتالية ولا تترك ثغرة', function () {
    $ids = static function (int $page): array {
        $window = CandidateWindow::forPage($page, 10);
        $ranked = array_map(static fn (int $i): object => (object) ['entry_id' => $i], range(1, $window->size));

        return array_map(static fn (object $r): int => $r->entry_id, $window->slice($ranked, 10));
    };

    expect($ids(1))->toBe(range(1, 10))
        ->and($ids(2))->toBe(range(11, 20))
        ->and($ids(3))->toBe(range(21, 30));
});

it('تسقف النافذة فلا يسحب الترقيم العميق آلاف الصفوف', function () {
    config(['search.retrieval.max_candidates' => 500]);

    $shallow = CandidateWindow::forPage(2, 15);
    $deep = CandidateWindow::forPage(200, 15);

    expect($shallow->size)->toBeLessThanOrEqual(500)
        ->and($shallow->rerank)->toBeTrue()
        ->and($deep->size)->toBe(15)
        ->and($deep->rerank)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| بناء تعبير BOOLEAN MODE
|--------------------------------------------------------------------------
*/

it('يبني سلّم تراخٍ من الأصرم إلى الأوسع', function () {
    $queries = $this->builder->build($this->analyzer->analyze('iphone pro max'));

    expect($queries)->toHaveCount(2)
        ->and($queries[0])->toContain('+')       // صارم: كل المصطلحات مطلوبة
        ->and($queries[1])->not->toContain('+'); // متراخٍ: أيّها يكفي
});

it('يعقّم محارف النحو من مصطلحات المستخدم', function () {
    /*
     | محارف + - > < ( ) ~ * " @ لها معنى تنفيذي في BOOLEAN MODE.
     | الإصدار السابق كان يلصق مخرَج النموذج اللغوي في التعبير مباشرةً،
     | فأي محرف يطلقه النموذج كان يغيّر دلالة الاستعلام.
     */
    $queries = $this->builder->build($this->analyzer->analyze('iphone" OR (1=1) -- @x*'));

    foreach ($queries as $query) {
        // "+" و"*" مسموحتان فقط حيث يضعهما البنّاء: بادئةً ولاحقةً.
        expect($query)->not->toContain('"')
            ->and($query)->not->toContain('(1')
            ->and($query)->not->toContain('@');
    }
});

it('يجمع المصطلح ومقابله الصوتي في مجموعة مطلوبة واحدة', function () {
    /*
     | فصلهما إلى شرطين مُلزَمين كان سيتطلّب أن يحتوي المستند الكلمة
     | بالعربية والإنجليزية معاً — وهو ما لا يقع في أي محتوى.
     */
    $strict = $this->builder->build($this->analyzer->analyze('ايفون'))[0];

    expect($strict)->toContain('ايفون')
        ->and($strict)->toContain('iphone')
        ->and($strict)->toStartWith('+(');
});

it('يُلحق الاستثناءات بكل درجات التراخي', function () {
    $queries = $this->builder->build($this->analyzer->analyze('laptop without charger'));

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $query) {
        expect($query)->toContain('-charger');
    }
});

it('لا يبني تعبيراً لخطة بلا مصطلحات', function () {
    // خطة استثناء بحت: يعالجها المستودع بمسار لا يمرّ بـ FULLTEXT.
    $plan = $this->analyzer->analyze('!!!');

    expect($this->builder->build($plan))->toBe([]);
});

it('يستهدف فهرس الـ ngram للغات بلا فواصل كلمات', function () {
    $chinese = $this->analyzer->analyze('苹果手机');
    $english = $this->analyzer->analyze('apple phone');

    expect($chinese->needsNgram)->toBeTrue()
        ->and($english->needsNgram)->toBeFalse()
        ->and($this->builder->build($chinese))->not->toBeEmpty();
});
