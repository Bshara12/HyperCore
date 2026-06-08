<?php

namespace Tests\Unit\Domains\CMS\Actions\Files;

use App\Domains\CMS\Actions\Files\UploadFileToSupabaseAction;
use App\Events\SystemLogEvent;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

test('it uploads file to supabase and logs the event', function () {
  // 1. تزييف الـ Storage والـ Event
  // نحدد القرص 'supabase' لأننا نستخدمه في الكلاس
  Storage::fake('supabase');
  Event::fake();

  // 2. تجهيز ملف وهمي
  $file = UploadedFile::fake()->create('test-image.jpg', 500); // ملف بحجم 500 كيلوبايت
  $path = 'test-directory';

  // 3. التنفيذ
  $action = new UploadFileToSupabaseAction();
  $resultPath = $action->execute($file, $path);

  // 4. التأكيدات

  // التأكد من أن الملف تم حفظه فعلياً في الـ disk الوهمي
  Storage::disk('supabase')->assertExists($resultPath);

  // التأكد من أن الحدث تم إطلاقه بالبيانات الصحيحة
  Event::assertDispatched(SystemLogEvent::class, function ($event) {
    return $event->eventType === 'upload_file'
      && $event->module === 'cms'
      && $event->entityType === 'file';
  });

  // التأكد من أن المسار الذي عاد يبدأ بالمسار الذي أرسلناه
  expect($resultPath)->toStartWith($path);
});
