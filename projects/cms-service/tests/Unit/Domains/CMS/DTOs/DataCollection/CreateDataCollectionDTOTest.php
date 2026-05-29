<?php

use App\Domains\CMS\DTOs\DataCollection\CreateDataCollectionDTO;
use App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface;
use App\Domains\CMS\Requests\CreateDataCollectionRequest;
use App\Models\Project; // استيراد المودل بشكل صحيح
use Illuminate\Support\Facades\App;

test('it creates DTO from request and resolves dependencies from container', function () {
    // 1. محاكاة المشروع الحالي (هذا يبقى كما هو)
    $mockProject = new class { public $public_id = 'abc-123'; };
    App::instance('currentProject', $mockProject);

    // 2. إنشاء كائن Project حقيقي
    $project = new \App\Models\Project();
    $project->id = 99;

    // 3. محاكاة الـ Repository (هذا يبقى كما هو)
    $repoMock = Mockery::mock(\App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface::class);
    $repoMock->shouldReceive('findByKey')
        ->with('abc-123')
        ->once()
        ->andReturn($project);
    App::instance(\App\Domains\CMS\Repositories\Interface\ProjectRepositoryInterface::class, $repoMock);

    // 4. الحل السحري: استخدم نسخة حقيقية من الـ Request بدلاً من الـ Mock
    $request = new \App\Domains\CMS\Requests\CreateDataCollectionRequest();
    $request->merge([
        'data_type_id' => 5,
        'name' => 'Test Collection',
        'slug' => 'test-slug',
        'type' => 'manual',
        'is_active' => true,
        'is_offer' => true,
        'conditions' => null,
        'settings' => null,
    ]);

    // 5. تنفيذ التحويل (سيعمل الآن بدون أخطاء BadMethodCall)
    $dto = \App\Domains\CMS\DTOs\DataCollection\CreateDataCollectionDTO::fromRequest($request);

    // 6. التحقق
    expect($dto->project_id)->toBe(99)
        ->and($dto->name)->toBe('Test Collection')
        ->and($dto->is_active)->toBeTrue();
});

test('it converts DTO to array correctly', function () {
  $dto = new CreateDataCollectionDTO(
    project_id: 1,
    data_type_id: 2,
    name: 'Name',
    slug: 'slug',
    type: 'type',
    conditions: null,
    conditions_logic: null,
    description: null,
    is_active: true,
    is_offer: false,
    settings: null
  );

  $array = $dto->CollectionToArray();

  expect($array)->toHaveKey('project_id', 1)
    ->and($array)->toHaveKey('name', 'Name');
});
