<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\ContentEntryNotFoundException;

test('it sets the correct exception message', function () {
  $contentId = 999;

  // إنشاء الاستثناء
  $exception = new ContentEntryNotFoundException($contentId);

  // التأكد من أن الرسالة مطابقة تماماً لما هو متوقع مع الـ ID
  expect($exception->getMessage())
    ->toBe('Data entry [999] not found or has no associated data type.');
});

test('it is an instance of RuntimeException', function () {
  $exception = new ContentEntryNotFoundException(1);

  // التأكد من أن الاستثناء يرث من RuntimeException كما هو محدد في الكود
  expect($exception)->toBeInstanceOf(\RuntimeException::class);
});
