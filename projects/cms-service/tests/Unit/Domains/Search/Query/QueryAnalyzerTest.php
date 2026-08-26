<?php

use App\Domains\Search\Support\Lexicon\Lexicon;
use App\Domains\Search\Support\Lexicon\ProjectSynonyms;
use App\Domains\Search\Support\Query\AttributeFilter;
use App\Domains\Search\Support\Query\Extractors\LexicalAttributeExtractor;
use App\Domains\Search\Support\Query\Extractors\NegationExtractor;
use App\Domains\Search\Support\Query\Extractors\NumericExtractor;
use App\Domains\Search\Support\Query\Extractors\TemporalExtractor;
use App\Domains\Search\Support\Query\QueryAnalyzer;
use App\Domains\Search\Support\Query\QueryPlan;

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
});

/** أوّل شرط على مفتاح معيّن في الخطة. */
function filterFor(QueryPlan $plan, string $key): ?AttributeFilter
{
    foreach ($plan->filters as $filter) {
        if ($filter->key === $key) {
            return $filter;
        }
    }

    return null;
}

/*
|--------------------------------------------------------------------------
| الزمن — الاستعلام المرجعي
|--------------------------------------------------------------------------
*/

it('يحوّل "نزل بال 2020" إلى شرط قاطع على السنة', function () {
    $plan = $this->analyzer->analyze('بدي الايفون يلي نزل بال 2020');

    $year = filterFor($plan, 'year');

    expect($year)->not->toBeNull()
        ->and($year->value)->toBe(2020.0)
        ->and($year->operator)->toBe(AttributeFilter::OP_EQUALS)
        ->and($year->isHard())->toBeTrue();
});

it('يفهم الاستعلام نفسه بالأرقام الهندية-العربية', function () {
    /*
     | "٢٠٢٠" و"2020" رمزان مختلفان تماماً على مستوى البايتات. بلا
     | توحيد الأرقام لا يطابق أحدهما الآخر أبداً — وهذا يعطّل البحث
     | العددي لنصف مستخدمي اللغة العربية.
     */
    $plan = $this->analyzer->analyze('الايفون يلي نزل بال ٢٠٢٠');

    expect(filterFor($plan, 'year')?->value)->toBe(2020.0);
});

it('يترك السنة المجرّدة ترجيحاً لا إقصاءً', function () {
    /*
     | "iphone 2020" غامضة: قد تعني سنة الإصدار وقد تكون اسم موديل.
     | جعلها شرطاً قاطعاً يُقصي "iPhone 2020 Edition" من نتائج البحث
     | عن اسمه الحرفي — وهو خطأ أسوأ من ترتيب دون الأمثل.
     */
    $plan = $this->analyzer->analyze('iphone 2020');

    $year = filterFor($plan, 'year');

    expect($year)->not->toBeNull()
        ->and($year->isHard())->toBeFalse()
        ->and($plan->terms)->toContain('2020');
});

it('يرفع السنة إلى شرط قاطع عند وجود دالّ زمني إنجليزي', function () {
    $plan = $this->analyzer->analyze('iphone released in 2020');

    expect(filterFor($plan, 'year')?->isHard())->toBeTrue();
});

it('يستهلك السنة المؤكَّدة فلا تُحتسب مرّتين', function () {
    /*
     | السنة المحوَّلة إلى شرط يجب أن تخرج من حساب الصلة النصّية،
     | وإلا احتُسبت مرّة كشرط ومرّة كمصطلح فتتقدّم مستنداتٌ تذكر
     | "2020" في متنها على مستنداتٍ صدرت فعلاً في 2020.
     */
    $plan = $this->analyzer->analyze('iphone released in 2020');

    expect($plan->terms)->not->toContain('2020');
});

it('يستخرج نطاق السنوات', function () {
    $plan = $this->analyzer->analyze('iphone 2018-2020');

    $year = filterFor($plan, 'year');

    expect($year?->operator)->toBe(AttributeFilter::OP_RANGE)
        ->and($year->value)->toBe(2018.0)
        ->and($year->valueTo)->toBe(2020.0);
});

it('يحسب الزمن النسبي من التقويم الحالي', function () {
    $plan = $this->analyzer->analyze('iphone from last year');

    expect(filterFor($plan, 'year')?->value)->toBe((float) (((int) date('Y')) - 1));
});

it('يتجاهل الأرقام الرباعية خارج مدى السنوات المعقول', function () {
    $plan = $this->analyzer->analyze('model 8500');

    expect(filterFor($plan, 'year'))->toBeNull()
        ->and($plan->terms)->toContain('8500');
});

/*
|--------------------------------------------------------------------------
| النفي
|--------------------------------------------------------------------------
*/

it('يفصل المستثنى عن المطلوب بالعربية', function () {
    $plan = $this->analyzer->analyze('ما بدي ايفون 14');

    expect($plan->mustNot)->toContain('14')
        ->and($plan->terms)->not->toContain('14');
});

it('يفصل المستثنى عن المطلوب بالإنجليزية', function () {
    $plan = $this->analyzer->analyze('iphone without case');

    expect($plan->terms)->toContain('iphone')
        ->and($plan->mustNot)->toContain('case');
});

it('يحدّ مدى النفي فلا يبتلع بقية الجملة', function () {
    /*
     | "بدون" تنفي ما يليها مباشرةً لا كل ما بعدها. المدى المفتوح
     | كان يحوّل استعلاماً مفهوماً إلى استثناء لكل شيء، فيعيد صفر
     | نتائج.
     */
    $plan = $this->analyzer->analyze('laptop without charger gaming rtx');

    expect($plan->mustNot)->toContain('charger')
        ->and($plan->terms)->toContain('gaming')
        ->and($plan->terms)->toContain('rtx');
});

it('يفضّل الدالّ الأطول عند تداخل الدوالّ', function () {
    /*
     | "ما بدي" و"بدي" كلاهما يطابق بداية النص. لو جُرِّب الأقصر أولاً
     | لالتُقط "بدي" حشواً وضاع النفي كلياً — فينقلب معنى الاستعلام.
     */
    $plan = $this->analyzer->analyze('ما بدي سامسونج');

    expect($plan->mustNot)->toContain('سامسونج');
});

/*
|--------------------------------------------------------------------------
| الأرقام والسمات
|--------------------------------------------------------------------------
*/

it('يحوّل الوحدات إلى سمات عددية', function () {
    $plan = $this->analyzer->analyze('iphone 128gb');

    expect(filterFor($plan, 'storage')?->value)->toBe(128.0);
});

it('يوحّد الوحدات إلى مقياس واحد', function () {
    /*
     | 1tb مخزَّنةً "1" أصغر من 512gb في أي مقارنة عددية. التوحيد إلى
     | الميغابايت هو ما يجعل الترتيب والمقارنة صحيحين.
     */
    $plan = $this->analyzer->analyze('laptop 1tb');

    expect(filterFor($plan, 'storage')?->value)->toBe(1024.0);
});

it('يستخرج السعر من رمز العملة', function () {
    $plan = $this->analyzer->analyze('iphone $500');

    expect(filterFor($plan, 'price')?->value)->toBe(500.0);
});

it('يستخرج حدّاً أعلى للسعر', function () {
    $plan = $this->analyzer->analyze('cheap laptop under 800');

    $price = filterFor($plan, 'price');

    expect($price?->operator)->toBe(AttributeFilter::OP_LTE)
        ->and($price->value)->toBe(800.0);
});

it('لا يخترع سمة لرقم مجرّد', function () {
    /*
     | القاعدة الحاكمة: لا يُنتَج شرط عددي إلا حين نستطيع تسمية ما
     | يقيسه الرقم. "iphone 15" رقمه اسم موديل لا مقدار، وتحويله إلى
     | شرط كان سيُقصي "iPhone 15 Pro" من نتائج البحث عن "iphone 15".
     */
    $plan = $this->analyzer->analyze('iphone 15');

    expect($plan->filters)->toBeEmpty()
        ->and($plan->terms)->toContain('15');
});

it('يحوّل اللون العربي إلى سمة إنجليزية', function () {
    /*
     | المحتوى مُدخَل بالإنجليزية غالباً ("Color: Black")، فالبحث
     | النصّي عن "اسود" لا يطابق شيئاً مهما بلغت جودة المُرتِّب.
     */
    $plan = $this->analyzer->analyze('ايفون اسود');

    expect(filterFor($plan, 'color')?->value)->toBe('black');
});

/*
|--------------------------------------------------------------------------
| النية والتوسعة
|--------------------------------------------------------------------------
*/

it('يكشف النية بالعربية والإنجليزية سواء', function (string $query, string $expected) {
    expect($this->analyzer->analyze($query)->intent['intent'])->toBe($expected);
})->with([
    ['iphone price', 'buy'],
    ['سعر ايفون', 'buy'],
    ['fix broken screen', 'repair'],
    ['تصليح شاشة', 'repair'],
    ['samsung vs iphone', 'compare'],
    ['how to install', 'learn'],
    ['iphone', 'general'],
]);

it('يضيف المقابل اللاتيني كتوسعة لا كمصطلح', function () {
    /*
     | من كتب "ايفون" يريد الآيفون، لكن مطابقة "iphone" استنتاجٌ منّا
     | لا نصٌّ منه — فلا يصحّ أن تُساوي في الوزن ما كتبه فعلاً.
     */
    $plan = $this->analyzer->analyze('ايفون برو');

    expect($plan->terms)->toContain('ايفون')
        ->and($plan->expansions)->toContain('iphone')
        ->and($plan->expansions)->toContain('pro');
});

/*
|--------------------------------------------------------------------------
| المتانة
|--------------------------------------------------------------------------
*/

it('لا يُفرغ استعلاماً يتكوّن كلّه من كلمات وقف', function () {
    /*
     | حذف كل كلمات الوقف من "how to" يترك خطة فارغة ترجع صفر نتائج
     | على استعلام له معنى. نتيجة ضعيفة أفضل من لا نتيجة.
     */
    expect($this->analyzer->analyze('how to')->terms)->not->toBeEmpty();
});

it('ينتج خطة قابلة للتنفيذ للغات بلا ملف موارد', function () {
    /*
     | لغة بلا معجم تبقى مدعومة بالتطبيع والتقسيم والترتيب. الملف
     | يحسّن الجودة ولا يشترط التشغيل.
     */
    $russian = $this->analyzer->analyze('дешевый смартфон');
    $chinese = $this->analyzer->analyze('苹果手机');

    expect($russian->isExecutable())->toBeTrue()
        ->and($russian->terms)->toHaveCount(2)
        ->and($russian->terms)->toContain('смартфон')
        ->and($chinese->isExecutable())->toBeTrue()
        ->and($chinese->needsNgram)->toBeTrue();
});

it('يطوي الحروف الكيريلية المزخرفة إلى أصولها', function () {
    /*
     | "дешевый" تصل مطويّة إلى "дешевыи": الحرف "й" هو "и" مع علامة
     | مركَّبة (U+0306)، فيطويها التطبيع كما يطوي "é" إلى "e".
     |
     | هذا مقصود لا عرَضي. الروس يكتبون "е" مكان "ё" باستمرار، والطيّ
     | يجعل الصورتين تتطابقان في البحث. والأهم أنه يُطبَّق على جانبَي
     | الفهرسة والاستعلام معاً، فيبقى "дешевый" يطابق "дешевый" —
     | الطيّ يوسّع المطابقة ولا يكسرها.
     */
    $plan = $this->analyzer->analyze('дешевый');

    expect($plan->terms)->toBe(['дешевыи'])
        ->and($plan->isExecutable())->toBeTrue();
});

it('يبني عبارة للمطابقة المتجاورة', function () {
    expect($this->analyzer->analyze('iphone 15 pro')->phrases)->toBe(['iphone 15 pro']);
});

it('يعالج المدخلات الفارغة والرمزية بلا انهيار', function () {
    expect($this->analyzer->analyze('')->isExecutable())->toBeFalse()
        ->and($this->analyzer->analyze('   ')->isExecutable())->toBeFalse()
        ->and($this->analyzer->analyze('!!!???')->isExecutable())->toBeFalse();
});

it('يميّز الصياغة الطبيعية عن الكلمات المفتاحية', function () {
    expect($this->analyzer->analyze('بدي ايفون')->isNaturalLanguage)->toBeTrue()
        ->and($this->analyzer->analyze('iphone 15')->isNaturalLanguage)->toBeFalse();
});
