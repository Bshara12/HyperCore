<?php

use App\Domains\CMS\Support\LanguageResolver;

beforeEach(function () {
  $this->resolver = new LanguageResolver();
});

test('it returns the requested language when provided', function () {
  $result = $this->resolver->resolve('ar');

  expect($result)->toBe('ar');
});

test('it returns the configured fallback locale when requested is null', function () {
  // إعداد قيمة افتراضية في الـ config للاختبار
  config(['app.fallback_locale' => 'fr']);

  $result = $this->resolver->resolve(null);

  expect($result)->toBe('fr');
});

test('it returns default "en" if fallback_locale is not configured', function () {
  // إفراغ الإعدادات للتأكد من عمل القيمة الافتراضية 'en'
  config(['app.fallback_locale' => null]);

  $result = $this->resolver->resolve(null);

  expect($result)->toBe('en');
});
