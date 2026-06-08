<?php

namespace Tests\Unit\Domains\CMS\Actions\DataCollection;

use App\Domains\CMS\Actions\DataCollection\GenerateDynamicItemsAction;
use App\Domains\CMS\Repositories\Interface\DataCollectionRepositoryInterface;
use App\Domains\CMS\Services\DynamicCollectionQueryBuilder;
use App\Models\DataCollection;
use Mockery;

afterEach(function () {
  Mockery::close();
});

test('it builds dynamic entries and creates items for each', function () {
  // 1. إنشاء نسخة من الموديل الحقيقي بدلاً من stdClass
  $collection = new DataCollection();
  $collection->id = 10;

  // محاكاة إرجاع مدخلات من الـ Builder
  $entries = [
    (object) ['id' => 100],
    (object) ['id' => 200],
  ];

  // 2. تجهيز الموكات (Mocks)
  $repoMock = Mockery::mock(DataCollectionRepositoryInterface::class);
  $builderMock = Mockery::mock(DynamicCollectionQueryBuilder::class);

  // الآن الموك يقبل الـ DataCollection موديل
  $builderMock->shouldReceive('build')
    ->once()
    ->with($collection)
    ->andReturn($entries);

  // ... باقي الكود كما هو تماماً
  $repoMock->shouldReceive('createDataCollectionItem')
    ->twice() // بما أننا نتوقع عنصرين
    ->with(Mockery::any()); // يمكن استخدام any() للتبسيط أو كتابة الـ with() التفصيلية

  // 3. التنفيذ
  $action = new GenerateDynamicItemsAction($repoMock, $builderMock);
  $result = $action->execute($collection);

  // 4. التأكيدات
  expect($result)->toBe($entries);
});
