<?php

use App\Domains\CMS\StrategyCheck\FileFieldValidator;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
  $this->validator = new FileFieldValidator();
  // محاكاة كائن ملف مرفوع
  $this->file = Mockery::mock(UploadedFile::class);
});

test('it passes validation when file is valid', function () {
  // إعداد الـ Mock ليرجع قيمة صحيحة
  $this->file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
  $this->file->shouldReceive('getSize')->andReturn(100);

  $config = ['mimes' => 'jpg,png', 'max' => 500];

  // يجب ألا يرمي استثناءً
  expect(fn() => $this->validator->validate($this->file, $config))->not->toThrow(Exception::class);
});

test('it throws exception when mime type is not allowed', function () {
  $this->file->shouldReceive('getClientOriginalExtension')->andReturn('pdf');

  $config = ['name' => 'avatar', 'mimes' => 'jpg,png'];

  expect(fn() => $this->validator->validate($this->file, $config))
    ->toThrow(Exception::class, 'Field avatar must be one of the following types: jpg, png');
});

test('it throws exception when file size exceeds limit', function () {
  $this->file->shouldReceive('getClientOriginalExtension')->andReturn('jpg');
  $this->file->shouldReceive('getSize')->andReturn(1000); // حجم أكبر من المسموح

  $config = ['name' => 'image', 'max' => 500];

  expect(fn() => $this->validator->validate($this->file, $config))
    ->toThrow(Exception::class, 'Field image exceeds the maximum allowed size of 500 bytes.');
});

test('it uses default field name when not provided in config', function () {
  $this->file->shouldReceive('getClientOriginalExtension')->andReturn('pdf');

  $config = ['mimes' => 'jpg']; // لا يوجد name

  expect(fn() => $this->validator->validate($this->file, $config))
    ->toThrow(Exception::class, 'Field file must be one of the following types: jpg');
});
