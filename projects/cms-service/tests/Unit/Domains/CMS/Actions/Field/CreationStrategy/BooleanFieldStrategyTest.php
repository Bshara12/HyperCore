<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\BooleanFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->strategy = new BooleanFieldStrategy();
});

test('it allows valid rules for boolean field', function () {
    // الاختبار لا يجب أن يرمي أي خطأ
    $this->strategy->validateRules(['boolean', 'required']);
    expect(true)->toBeTrue();
});

test('it throws 422 for invalid rules', function () {
    expect(fn() => $this->strategy->validateRules(['boolean', 'min:10']))
        ->toThrow(HttpException::class, "Rule 'min:10' is not allowed for boolean field.");
});

test('it normalizes settings correctly', function () {
    // حالة: القيمة موجودة
    $settings = ['default' => '1'];
    $result = $this->strategy->normalizeSettings($settings);
    expect($result['default'])->toBeTrue();

    // حالة: القيمة مفقودة (تأكد من الافتراضي)
    $result = $this->strategy->normalizeSettings([]);
    expect($result['default'])->toBeFalse();
});