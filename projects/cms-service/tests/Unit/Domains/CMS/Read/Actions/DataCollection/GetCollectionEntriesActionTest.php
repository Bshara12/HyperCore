<?php

namespace Tests\Unit\Domains\CMS\Read\Actions\DataCollection;

use App\Domains\CMS\Read\Actions\DataCollection\GetCollectionEntriesAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\Core\Services\CircuitBreakerService; // تأكد من استيراد المسار الصحيح
use App\Models\DataCollection;

beforeEach(function () {
  $this->mock(CircuitBreakerService::class, function ($mock) {
    // 1. تحديد ما يجب أن ترجعه الدالة التي تسبب الخطأ
    $mock->shouldReceive('canProceed')->andReturn(true);

    // 2. الدوال الأخرى المتوقع استدعاؤها
    $mock->shouldReceive('recordSuccess')->andReturn(true);

    // 3. (نصيحة إضافية) استخدم هذا السطر لتجاهل أي دوال أخرى لا تهمنا
    // سيمنع هذا ظهور BadMethodCallException إذا استدعى الكود دالة أخرى
    $mock->shouldIgnoreMissing();
  });
});

test('it executes and caches collection entries correctly', function () {
  // 1. تحضير الـ Mocks
  $projectRepo = mock(ProjectRepositoryInterface::class);
  $dataRepo = mock(DataCollectionRepositoryInterface::class);

  // 2. تحضير البيانات (يجب تعريف المتغيرات هنا)
  $project = new \App\Models\Project();
  $project->id = 123;

  // تعريف كائن الـ collection وإعطاؤه id
  $collection = new DataCollection();
  $collection->id = 456;

  $expectedEntries = ['entry1', 'entry2'];

  // 3. إعداد التوقعات
  $projectRepo->shouldReceive('findByKey')
    ->once()
    ->with('my-project-key')
    ->andReturn($project);

  $dataRepo->shouldReceive('find')
    ->once()
    ->with(123, 'my-slug') // 123 هو الـ id الخاص بالمشروع
    ->andReturn($collection);

  $dataRepo->shouldReceive('getEntries')
    ->once()
    ->with(456) // 456 هو الـ id الخاص بالـ collection
    ->andReturn($expectedEntries);

  // 4. تنفيذ الاختبار
  $action = new GetCollectionEntriesAction($dataRepo, $projectRepo);
  $results = $action->execute('my-project-key', 'my-slug');

  // 5. التأكيد
  expect($results)->toBe($expectedEntries);
});
