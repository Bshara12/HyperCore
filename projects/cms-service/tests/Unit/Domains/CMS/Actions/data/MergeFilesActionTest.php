<?php

namespace Tests\Unit\Domains\CMS\Actions\data;

use App\Domains\CMS\Actions\data\MergeFilesAction;
use App\Domains\CMS\Services\FileUploadService;
use Illuminate\Http\UploadedFile; // استيراد ضروري
use Mockery;

test('it correctly uploads files and merges paths into values array', function () {
  // 1. Arrange
  $uploaderMock = Mockery::mock(FileUploadService::class);

  $projectId = 100;
  $dataTypeId = 50;
  $fieldId = 10;
  $lang = 'en';

  // إنشاء ملف مزيف مطابق لما يتوقعه Service
  $fakeFile = UploadedFile::fake()->image('image.png');
  $mockedPath = 'storage/path/image.png';

  // نتوقع استدعاء دالة الرفع باستخدام كائن الـ $fakeFile نفسه
  $uploaderMock->shouldReceive('upload')
    ->once()
    ->with($fakeFile, $projectId, $dataTypeId, $fieldId)
    ->andReturn($mockedPath);

  $action = new MergeFilesAction($uploaderMock);

  // تجهيز البيانات
  $values = [
    $fieldId => [
      $lang => ['existing_file.jpg']
    ]
  ];

  // تمرير الملف المزيف داخل المصفوفة
  $files = [
    $fieldId => [
      $lang => [$fakeFile]
    ]
  ];

  // 2. Act
  $result = $action->execute($values, $files, $projectId, $dataTypeId);

  // 3. Assert
  expect($result[$fieldId][$lang])
    ->toHaveCount(2)
    ->toContain('existing_file.jpg', $mockedPath);
});
