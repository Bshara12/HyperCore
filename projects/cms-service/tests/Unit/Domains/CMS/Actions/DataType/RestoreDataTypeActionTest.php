<?php

namespace Tests\Unit\Domains\CMS\Actions\DataType;

use App\Domains\CMS\Actions\DataType\RestoreDataTypeAction;
use App\Domains\CMS\Repositories\Interface\DataTypeRepositoryInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

afterEach(function () {
  Mockery::close();
});

test('it restores a datatype by its id', function () {
  // 1. تجهيز المعطيات
  $dataTypeId = 99;

  // 2. تجهيز الـ Mock للـ Repository
  $repoMock = Mockery::mock(DataTypeRepositoryInterface::class);

  // نتوقع استدعاء restore بالـ ID الممرر
  $repoMock->shouldReceive('restore')
    ->once()
    ->with($dataTypeId);

  // 3. التنفيذ
  $action = new RestoreDataTypeAction($repoMock);
  $action->execute($dataTypeId);

  // ملاحظة: بما أننا نعتمد على Mockery، فإذا لم يتم استدعاء الـ restore 
  // سيفشل الاختبار تلقائياً بفضل Mockery::close()
});
