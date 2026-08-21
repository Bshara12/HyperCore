<?php

use App\Domains\Search\Support\ArabicTextNormalizer;

test('يوحّد أشكال الألف فتتطابق آيفون مع ايفون', function () {
    expect(ArabicTextNormalizer::normalize('آيفون'))->toBe('ايفون')
        ->and(ArabicTextNormalizer::normalize('أيفون'))->toBe('ايفون')
        ->and(ArabicTextNormalizer::normalize('إيفون'))->toBe('ايفون')
        ->and(ArabicTextNormalizer::normalize('ايفون'))->toBe('ايفون');
});

test('يُزيل التشكيل والتطويل', function () {
    expect(ArabicTextNormalizer::normalize('كَفَر'))->toBe('كفر')
        ->and(ArabicTextNormalizer::normalize('هــاتف'))->toBe('هاتف')
        ->and(ArabicTextNormalizer::normalize('مُحَمَّد'))->toBe('محمد');
});

test('يوحّد الياء والتاء المربوطة والواو المهموزة', function () {
    expect(ArabicTextNormalizer::normalize('مصطفى'))->toBe('مصطفي')
        ->and(ArabicTextNormalizer::normalize('ساعة'))->toBe('ساعه')
        ->and(ArabicTextNormalizer::normalize('مسؤول'))->toBe('مسوول')
        ->and(ArabicTextNormalizer::normalize('مسئول'))->toBe('مسيول');
});

test('يُحوّل الأرقام العربية-الهندية إلى ASCII', function () {
    expect(ArabicTextNormalizer::normalize('ايفون ١٥'))->toBe('ايفون 15')
        ->and(ArabicTextNormalizer::normalize('۱۴ برو'))->toBe('14 برو');
});

test('يُصغّر الحروف اللاتينية ويوحّد المسافات', function () {
    expect(ArabicTextNormalizer::normalize("  iPhone   15\nPro  "))->toBe('iphone 15 pro');
});

test('التطبيع idempotent — تطبيقه مرتين لا يُغيّر النتيجة', function () {
    $once = ArabicTextNormalizer::normalize('آيفون ١٥ برو مَاكس');

    expect(ArabicTextNormalizer::normalize($once))->toBe($once);
});

test('لا يمسّ علامات BOOLEAN MODE لأن نفس الدالة تُطبّع الـ query', function () {
    expect(ArabicTextNormalizer::normalize('+(آيفون* iphone*) -كفر'))
        ->toBe('+(ايفون* iphone*) -كفر');
});

test('normalizeToken يُزيل المسافات الداخلية', function () {
    expect(ArabicTextNormalizer::normalizeToken(' آيفون '))->toBe('ايفون');
});

test('tokenize يُرجع كلمات مُطبَّعة فريدة', function () {
    $tokens = ArabicTextNormalizer::tokenize('آيفون 15 برو ماكس - أفضل سعر، آيفون');

    expect($tokens)->toContain('ايفون')
        ->and($tokens)->toContain('افضل')
        ->and($tokens)->toContain('15')
        ->and(array_count_values($tokens)['ايفون'])->toBe(1);
});

test('looseRegex يُطابق النص الخام بكل أشكال الهمزة والتشكيل', function () {
    $pattern = '/' . ArabicTextNormalizer::looseRegex('ايفون') . '/u';

    expect(preg_match($pattern, 'آيفون 15 برو ماكس'))->toBe(1)
        ->and(preg_match($pattern, 'أيفون'))->toBe(1)
        ->and(preg_match($pattern, 'ايــفون'))->toBe(1)
        ->and(preg_match($pattern, 'سامسونج'))->toBe(0);
});

test('hasArabic يُميّز النص العربي', function () {
    expect(ArabicTextNormalizer::hasArabic('ايفون'))->toBeTrue()
        ->and(ArabicTextNormalizer::hasArabic('iphone'))->toBeFalse();
});
