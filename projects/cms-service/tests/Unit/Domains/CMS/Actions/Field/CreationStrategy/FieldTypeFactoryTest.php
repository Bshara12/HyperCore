<?php

namespace Tests\Unit\Domains\CMS\Actions\Field\CreationStrategy;

use App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory;
use App\Domains\CMS\Actions\Field\CreationStrategy\TextFieldStrategy;
use App\Domains\CMS\Actions\Field\CreationStrategy\BooleanFieldStrategy;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('factory returns correct strategy class', function ($type, $expectedClass) {
    // 1. إنشاء الكائن أولاً
    $factory = new \App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory();
    
    // 2. الاستدعاء على الكائن
    $strategy = $factory->make($type);

    expect($strategy)->toBeInstanceOf($expectedClass);
})->with([
    ['text', \App\Domains\CMS\Actions\Field\CreationStrategy\TextFieldStrategy::class],
    ['number', \App\Domains\CMS\Actions\Field\CreationStrategy\NumberFieldStrategy::class],
    ['boolean', \App\Domains\CMS\Actions\Field\CreationStrategy\BooleanFieldStrategy::class],
    ['select', \App\Domains\CMS\Actions\Field\CreationStrategy\SelectFieldStrategy::class],
    ['json', \App\Domains\CMS\Actions\Field\CreationStrategy\JsonFieldStrategy::class],
    ['relation', \App\Domains\CMS\Actions\Field\CreationStrategy\RelationFieldStrategy::class],
    ['file', \App\Domains\CMS\Actions\Field\CreationStrategy\FileFieldStrategy::class],
]);

test('factory throws 422 for unsupported type', function () {
    // 1. إنشاء الكائن أولاً
    $factory = new \App\Domains\CMS\Actions\Field\CreationStrategy\FieldTypeFactory();
    
    expect(fn () => $factory->make('invalid_type'))
        ->toThrow(HttpException::class, "Unsupported field type 'invalid_type'.");
});