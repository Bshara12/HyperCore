<?php

use App\Domains\Search\Support\Text\Segmenter;
use App\Domains\Search\Support\Text\TextFolder;
use App\Domains\Search\Support\Text\UnicodeScript;

/*
|--------------------------------------------------------------------------
| التطبيع
|--------------------------------------------------------------------------
|
| كل زوج هنا يمثّل صورتين يكتبهما المستخدمون بالتبادل. فشل أي منها
| يعني أن الاستعلام لا يطابق المحتوى رغم أنهما الشيء نفسه.
*/

dataset('equivalent pairs', [
    'التشكيل العربي' => ['قَهْوَة', 'قهوه'],
    'همزة الألف' => ['آيفون', 'ايفون'],
    'الياء الفارسية' => ['کتاب', 'كتاب'],
    'التاء المربوطة' => ['سياره', 'سيارة'],
    'علامات لاتينية' => ['café', 'cafe'],
    'ألمانية' => ['straße', 'strasse'],
    'أحرف كاملة العرض' => ['ＩＰＨＯＮＥ', 'iphone'],
    'أرقام هندية-عربية' => ['٢٠٢٠', '2020'],
    'أرقام فارسية' => ['۲۰۲۰', '2020'],
    'أرقام تايلندية' => ['๒๐๒๐', '2020'],
    'كاتاكانا/هيراغانا' => ['カタカナ', 'かたかな'],
    'السيغما النهائية' => ['Ελλάς', 'ελλας'],
    'حالة الأحرف' => ['IPhone 15 PRO', 'iphone 15 pro'],
]);

it('يوحّد الصور المتكافئة عبر اللغات', function (string $a, string $b) {
    expect(TextFolder::fold($a))->toBe(TextFolder::fold($b));
})->with('equivalent pairs');

it('يبقي الكلمات المختلفة مختلفة', function () {
    expect(TextFolder::fold('iphone'))->not->toBe(TextFolder::fold('ipad'))
        ->and(TextFolder::fold('2020'))->not->toBe(TextFolder::fold('2021'));
});

it('لا يهدم علامات الحركات الهندية', function () {
    /*
     | في الديفاناغاري علامات الحركات حروف كاملة المعنى لا زخارف:
     | "क" و"का" كلمتان مختلفتان. حذفها — كما يفعل التطبيع الساذج —
     | يمحو الكلمة ويترك هيكلاً ساكناً لا يطابق شيئاً.
     */
    expect(TextFolder::fold('हिन्दी'))->toBe('हिन्दी');
});

/*
|--------------------------------------------------------------------------
| كشف نظام الكتابة
|--------------------------------------------------------------------------
*/

it('يكشف الـ script المهيمن', function (string $text, string $expected) {
    expect(UnicodeScript::dominant($text))->toBe($expected);
})->with([
    ['iPhone 15 Pro', UnicodeScript::LATIN],
    ['قهوة عربية', UnicodeScript::ARABIC],
    ['Привет мир', UnicodeScript::CYRILLIC],
    ['我想买手机', UnicodeScript::HAN],
    ['아이폰 구매', UnicodeScript::HANGUL],
    ['สมาร์ทโฟน', UnicodeScript::THAI],
    ['עברית', UnicodeScript::HEBREW],
    ['हिन्दी', UnicodeScript::DEVANAGARI],
]);

it('لا يحسب الأرقام في نسبة الـ script', function () {
    // "iPhone 15" لاتيني بالكامل، لا لاتيني بأغلبية.
    expect(UnicodeScript::profile('iPhone 15'))
        ->toBe([UnicodeScript::LATIN => 1.0]);
});

it('يكشف الاستعلام المختلط', function () {
    expect(UnicodeScript::isMixed('iphone سعر'))->toBeTrue()
        ->and(UnicodeScript::isMixed('iphone price'))->toBeFalse();
});

it('يوجّه اللغات بلا فواصل كلمات إلى الـ ngram', function (string $text, bool $expected) {
    expect(UnicodeScript::needsNgram($text))->toBe($expected);
})->with([
    'صيني' => ['我想买手机', true],
    'ياباني' => ['スマートフォン', true],
    'تايلندي' => ['สมาร์ทโฟน', true],
    'خميري' => ['ទូរស័ព្ទ', true],
    'إنجليزي' => ['smartphone', false],
    'عربي' => ['هاتف ذكي', false],
    'كوري' => ['스마트폰', false],
]);

/*
|--------------------------------------------------------------------------
| التقسيم
|--------------------------------------------------------------------------
*/

it('يقسّم اللغات ذات المسافات على حدود الكلمات', function () {
    expect(Segmenter::tokenize(TextFolder::fold('iPhone 15 Pro-Max')))
        ->toBe(['iphone', '15', 'pro', 'max']);
});

it('يقسّم الصينية إلى n-grams', function () {
    /*
     | الـ parser الافتراضي في MySQL يرى "苹果手机" رمزاً واحداً فلا
     | يطابقه إلا استعلام مطابق حرفياً. الـ bigrams تجعل "手机" وحدها
     | تطابق — وهي الكلمة التي يبحث بها المستخدم فعلاً.
     */
    expect(Segmenter::tokenize(TextFolder::fold('苹果手机')))
        ->toBe(['苹果', '果手', '手机']);
});

it('يمرّر الـ n-grams عبر حدود الـ script في اليابانية', function () {
    /*
     | اليابانية تخلط ثلاثة scripts داخل الكلمة الواحدة. لو عوملت
     | المقاطع منفصلة لما عبر أي bigram الحدود، فتضيع "語の" و"のス"
     | وهي التي تحمل البنية النحوية.
     */
    $tokens = Segmenter::tokenize(TextFolder::fold('日本語のスマホ'));

    expect($tokens)->toContain('日本')
        ->and($tokens)->toContain('語の')
        ->and($tokens)->toContain('のす');
});

it('يحافظ على الكلمة الهندية سليمة عند التقسيم', function () {
    expect(Segmenter::tokenize(TextFolder::fold('हिन्दी में खोज')))
        ->toBe(['हिन्दी', 'में', 'खोज']);
});

it('يبقي الحرف الواحد قابلاً للبحث في اللغات الآسيوية', function () {
    // إسقاط ما هو أقصر من bigram يعني فقدان البحث بحرف واحد كلياً.
    expect(Segmenter::tokenize(TextFolder::fold('书')))->toBe(['书']);
});

it('يفرد نصّ الـ ngram للمحتوى الآسيوي فقط', function () {
    expect(Segmenter::ngramText(TextFolder::fold('苹果手机')))->toBe('苹果手机')
        ->and(Segmenter::ngramText(TextFolder::fold('iphone pro')))->toBeNull();
});

it('يعيد مصفوفة فارغة للنص الفارغ', function () {
    expect(Segmenter::tokenize(''))->toBe([])
        ->and(TextFolder::fold('   '))->toBe('');
});
