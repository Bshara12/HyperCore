<?php

use App\Domains\CMS\Actions\Files\UploadFileToSupabaseAction;
use App\Domains\CMS\Services\FileUploadService;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
  // إنشاء Mock للـ Action
  $this->uploadAction = Mockery::mock(UploadFileToSupabaseAction::class);

  // إنشاء الـ Service مع حقن الـ Mock
  $this->service = new FileUploadService($this->uploadAction);
});

afterEach(function () {
  Mockery::close();
});

test('it uploads file with correct generated path', function () {
  // 1. Arrange: تجهيز البيانات
  $file = UploadedFile::fake()->create('avatar.jpg', 500, 'image/jpeg');
  $projectId = 1;
  $dataTypeId = 5;
  $fieldId = 10;

  // هذا هو المسار المتوقع الذي يجب أن يقوم الـ Service ببنائه
  $expectedPath = 'projects/1/data-types/5/fields/10';
  $mockUrl = 'https://supabase.storage/projects/1/data-types/5/fields/10/avatar.jpg';

  // 2. Expectation: التأكد أن الـ Action سيُستدعى بالمسار الصحيح والمفروض
  $this->uploadAction->shouldReceive('execute')
    ->once()
    ->with($file, $expectedPath)
    ->andReturn($mockUrl);

  // 3. Act: تنفيذ العملية
  $result = $this->service->upload($file, $projectId, $dataTypeId, $fieldId);

  // 4. Assert: التحقق من النتيجة
  expect($result)->toBe($mockUrl);
});
