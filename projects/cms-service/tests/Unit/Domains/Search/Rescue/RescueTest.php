<?php

use App\Domains\Search\Support\Rescue\EditDistance;
use App\Domains\Search\Support\Rescue\KeyboardLayoutMapper;
use App\Domains\Search\Support\Rescue\VocabularyMatcher;
use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| المسافة التحريرية
|--------------------------------------------------------------------------
*/

it('يقيس المسافة بالمحارف لا بالبايتات', function () {
    /*
     | levenshtein() المدمجة تعمل على البايتات، والحرف العربي بايتان في
     | UTF-8. فتقيس "هاتف" و"هتف" بمسافة 2 والصحيح 1 — أي أن كل كلمة
     | غير لاتينية تبدو ضعف بُعدها فتسقط خارج أي عتبة تصحيح معقولة.
     */
    expect(EditDistance::between('هاتف', 'هتف'))->toBe(1)
        ->and(EditDistance::between('كتاب', 'كتب'))->toBe(1)
        ->and(EditDistance::between('手机', '手表'))->toBe(1);
});

it('يعدّ تبادل حرفين متجاورين عملية واحدة', function () {
    /*
     | أشيع خطأ مطبعي هو تبادل حرفين. ليفنشتاين تعدّه خطأين فيسقط خارج
     | عتبة الخطأ الواحد رغم أنه أقرب الأخطاء إلى الصواب.
     */
    expect(EditDistance::between('iphoen', 'iphone'))->toBe(1)
        ->and(EditDistance::between('teh', 'the'))->toBe(1);
});

it('ينهي القياس مبكراً عند تجاوز السقف', function () {
    // فارق الطول وحده يكفي للحكم، فلا داعي لبناء المصفوفة كاملة.
    expect(EditDistance::between('ab', 'abcdefghij', 2))->toBeGreaterThan(2);
});

it('يتدرّج التسامح مع طول الكلمة', function () {
    /*
     | خطأ واحد في كلمة من ثلاثة محارف يغيّر ثلثها — وكلمتان بهذا القرب
     | غالباً كلمتان مختلفتان لا خطأ مطبعي.
     */
    expect(EditDistance::toleranceFor(3))->toBe(0)
        ->and(EditDistance::toleranceFor(6))->toBe(1)
        ->and(EditDistance::toleranceFor(12))->toBe(2);
});

/*
|--------------------------------------------------------------------------
| تخطيط لوحة المفاتيح
|--------------------------------------------------------------------------
*/

it('يعكس الإنجليزية المكتوبة بلوحة عربية', function () {
    /*
     | "هحاخىث" ليست خطأ إملائياً ولا كلمة في أي لغة — هي "iphone"
     | بحروف موضعها نفسه على اللوحة. ولهذا لا يعالجها تصحيح الإملاء:
     | المسافة التحريرية بينهما تساوي طول الكلمتين معاً.
     */
    expect((new KeyboardLayoutMapper)->candidates('هحاخىث'))->toContain('iphone');
});

it('يعكس العربية المكتوبة بلوحة إنجليزية', function () {
    // الخطأ متناظر، وكلفة دعم الاتجاه الثاني عكس الخريطة نفسها.
    expect((new KeyboardLayoutMapper)->candidates('hgshum'))->toContain('الساعة');
});

it('يعمل على النصّ الخام لا المطبَّع', function () {
    /*
     | ى (مفتاح n) و ي (مفتاح d) يُطبَّعان إلى رمز واحد، فلو عُكس النصّ
     | بعد التطبيع لصار للمفتاحين مصدر واحد ولأنتج "ipho_de" بدل
     | "iphone" — أي أن التطبيع يمحو المعلومة التي يقوم عليها العكس.
     */
    $mapper = new KeyboardLayoutMapper;

    expect($mapper->candidates('هحاخىث'))->toContain('iphone')
        ->and($mapper->candidates(TextFolder::fold('هحاخىث')))->not->toContain('iphone');
});

it('لا يقترح شيئاً لنصّ لم يُكتب بلوحة خاطئة', function () {
    // العكس الناجح يبدّل الـ script المهيمن؛ وإلا فلم يُعكَس شيء ذو بال.
    expect((new KeyboardLayoutMapper)->candidates(''))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| التصحيح مقابل مفردات المشروع
|--------------------------------------------------------------------------
*/

function seedVocabulary(array $terms, int $projectId = 1, string $language = 'en'): void
{
    $rows = [];

    foreach ($terms as $term => $frequency) {
        $rows[] = [
            'project_id' => $projectId,
            'language' => $language,
            'term' => $term,
            'doc_freq' => $frequency,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    DB::table('search_term_stats')->insert($rows);
}

it('يصحّح إلى كلمة موجودة فعلاً في المحتوى', function () {
    /*
     | القاموس الثابت يصحّح إلى كلمات قد لا توجد في هذا المشروع، فيستبدل
     | خطأً بخطأ. المفردات المستخرَجة من الفهرس تضمن أن كل تصحيح يجد
     | نتائج بالضرورة.
     */
    seedVocabulary(['shoes' => 12, 'electronics' => 5, 'adapter' => 3]);

    $matcher = new VocabularyMatcher;

    expect($matcher->closest('shose', 1, 'en'))->toBe('shoes')
        ->and($matcher->closest('electronicss', 1, 'en'))->toBe('electronics');
});

it('يرجّح الأشيع عند تساوي المسافة', function () {
    // "hose" و"chose" و"shoes" كلها على مسافة واحدة من "shose".
    seedVocabulary(['shoes' => 40, 'chose' => 2, 'hose' => 1]);

    expect((new VocabularyMatcher)->closest('shose', 1, 'en'))->toBe('shoes');
});

it('يلتقط الخطأ الصوتي البعيد هجائياً', function () {
    /*
     | "smartfone" على مسافة ثلاث عمليات من "smartphones" — خارج أي
     | عتبة معقولة. لكن ph و f لهما الرمز الصوتي نفسه، فالمطابقة
     | الصوتية تلتقطها بقياس بُعد آخر: كيف تُنطق لا كيف تُهجّى.
     */
    seedVocabulary(['smartphones' => 8, 'tablets' => 4]);

    expect((new VocabularyMatcher)->closest('smartfone', 1, 'en'))->toBe('smartphones');
});

it('لا يصحّح سلسلة مكرَّرة طويلة إلى كلمة حقيقية', function () {
    /*
     | metaphone تطوي الحروف المكرَّرة، فمئتا حرف 'x' تُنتج رمزاً من
     | محرف واحد يقع على مسافة واحدة من نصف مفردات أي متن. بلا حارس
     | الطول كانت القمامة تُصحَّح إلى "shoes" وتعيد نتيجة.
     */
    seedVocabulary(['shoes' => 12]);

    expect((new VocabularyMatcher)->closest(str_repeat('x', 60), 1, 'en'))->toBeNull();
});

it('لا يصحّح كلمة قصيرة جداً', function () {
    // ما دون أربعة محارف يكون كل شيء قريباً من كل شيء.
    seedVocabulary(['shoes' => 12]);

    expect((new VocabularyMatcher)->closest('sho', 1, 'en'))->toBeNull();
});

it('لا يخلط مفردات مشروعين', function () {
    /*
     | المفردات مقسَّمة بالمشروع واللغة عمداً: التصحيح إلى كلمة من متجر
     | آخر يعيد صفر نتائج، فيكون أسوأ من عدم التصحيح.
     */
    seedVocabulary(['shoes' => 12], projectId: 1);
    seedVocabulary(['shoes' => 12], projectId: 2, language: 'ar');

    $matcher = new VocabularyMatcher;

    expect($matcher->closest('shose', 1, 'en'))->toBe('shoes')
        ->and($matcher->closest('shose', 3, 'en'))->toBeNull();
});

it('يعيد null حين لا يتغيّر شيء في القائمة', function () {
    seedVocabulary(['shoes' => 12]);

    expect((new VocabularyMatcher)->correctAll(['shoes'], 1, 'en'))->toBeNull();
});

it('لا ينهار حين تكون المفردات فارغة', function () {
    // مشروع جديد لم تُحسب إحصاءاته بعد.
    expect((new VocabularyMatcher)->closest('shose', 99, 'en'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| حدّ طول الوحدة
|--------------------------------------------------------------------------
*/

it('يُسقط الوحدات الأطول من حدّ الفهرسة في MySQL', function () {
    /*
     | innodb_ft_max_token_size = 84. الوحدة الأطول لا تدخل الفهرس
     | إطلاقاً، فلا يمكن أن تطابق شيئاً — وإبقاؤها يُنتج تعبير بحث
     | محكوماً بالفشل ويُغرق طبقة الإنقاذ بمدخلات لم تُصمَّم لها.
     */
    expect(Segmenter::tokenize(str_repeat('x', 200)))->toBe([])
        ->and(Segmenter::tokenize(str_repeat('x', 84)))->toHaveCount(1);
});
