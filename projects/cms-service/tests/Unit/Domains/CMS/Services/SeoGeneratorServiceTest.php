<?php

use App\Domains\CMS\Services\SeoGeneratorService;

beforeEach(function () {
  $this->service = new SeoGeneratorService();
});

test('it generates seo data correctly for a single language', function () {
  $values = [
    'title_field' => ['en' => 'Hello World']
  ];

  $result = $this->service->generate($values);

  expect($result)->toBe([
    'en' => [
      'meta_title' => 'Hello World',
      'slug' => 'hello-world'
    ]
  ]);
});

test('it ignores subsequent fields for the same language', function () {
  // هنا نختبر الـ ??=، يجب أن تبقى القيمة الأولى فقط
  $values = [
    'title_field' => ['en' => 'First Title'],
    'other_field' => ['en' => 'Second Title'],
  ];

  $result = $this->service->generate($values);

  expect($result['en']['meta_title'])->toBe('First Title')
    ->and($result['en']['slug'])->toBe('first-title');
});

test('it generates seo data for multiple languages', function () {
  $values = [
    'title_field' => [
      'en' => 'Hello',
      'ar' => 'مرحباً'
    ]
  ];

  $result = $this->service->generate($values);

  expect($result)->toHaveKey('en')
    ->toHaveKey('ar')
    ->and($result['ar']['meta_title'])->toBe('مرحباً');
});

test('it ignores non-string values', function () {
  $values = [
    'title_field' => ['en' => 12345], // قيمة رقمية يجب تجاهلها
    'real_title' => ['en' => 'Valid Title']
  ];

  $result = $this->service->generate($values);

  // بما أن 12345 ليست string، فلن يتم إضافتها، 
  // وبما أن 'Valid Title' جاءت لاحقاً ولم يتم تحديد 'meta_title' من قبل (بسبب تجاوز الرقمي)، 
  // فسوف يتم أخذ 'Valid Title'
  expect($result['en']['meta_title'])->toBe('Valid Title');
});

test('it returns empty array for empty input', function () {
  $result = $this->service->generate([]);

  expect($result)->toBe([]);
});
